<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Period;
use App\Models\RoomType;
use App\Services\Pricing\GuestInput;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\ReservationPricer;
use Carbon\Carbon;
use Database\Seeders\Camp2026Seeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Fiyat motorunun, sigortader.com.tr'de yayımlanan 2026 ücret tablolarıyla
 * birebir aynı sonucu ürettiğini doğrular.
 */
class PricingTest extends TestCase
{
    use RefreshDatabase;

    private ReservationPricer $pricer;

    /** Talep toplama dönemi — ilave ücret uygulanmayan tarih. */
    private Carbon $earlyApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingSeeder::class,
            CustomerGroupSeeder::class,
            FacilitySeeder::class,
            Camp2026Seeder::class,
        ]);

        $this->pricer = new ReservationPricer();
        $this->earlyApplication = Carbon::parse('2026-03-01');
    }

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

    private function room(string $facilitySlug, string $code): RoomType
    {
        $facility = Facility::where('slug', $facilitySlug)->firstOrFail();

        return RoomType::where('facility_id', $facility->id)->where('code', $code)->firstOrFail();
    }

    private function period(string $facilitySlug, int $number): Period
    {
        $facility = Facility::where('slug', $facilitySlug)->firstOrFail();

        return Period::where('facility_id', $facility->id)->where('number', $number)->firstOrFail();
    }

    private function groupId(string $code): int
    {
        return CustomerGroup::where('code', $code)->value('id');
    }

    /** Devre başlangıcında $age yaşında olacak bir kişi. */
    private function guest(string $groupCode, int $age = 40, bool $wantsMeal = false): GuestInput
    {
        return new GuestInput(
            customerGroupId: $this->groupId($groupCode),
            birthDate: Carbon::parse('2026-05-17')->subYears($age)->subMonths(2),
            wantsMeal: $wantsMeal,
            name: "{$groupCode} · {$age} yaş",
        );
    }

    /** @param list<GuestInput> $guests */
    private function quote(
        RoomType $room,
        Period $period,
        array $guests,
        ?Period $second = null,
        ?Carbon $applicationDate = null,
    ) {
        return $this->pricer->quote(new PricingInput(
            roomType: $room,
            period: $period,
            secondPeriod: $second,
            guests: $guests,
            applicationDate: $applicationDate ?? $this->earlyApplication,
        ));
    }

    // ---------------------------------------------------------------
    // Tablo 1 — oda ücretleri
    // ---------------------------------------------------------------

    public function test_colakli_indirimli_devrede_iki_yetiskin_birinci_grup(): void
    {
        // Tablo 1: Çolaklı 1. Devre (İndirimli), I. Grup → 2.150 TL/kişi/gün
        $quote = $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 1),
            [$this->guest('I'), $this->guest('I')],
        );

        $this->assertSame(6, $quote->nights);
        $this->assertSame(2 * 2150.0 * 6, $quote->accommodationTotal);
        $this->assertSame(0.0, $quote->emptyBedTotal);
        $this->assertSame(25800.0, $quote->total);
    }

    public function test_musteri_gruplari_farkli_ucretlendirilir(): void
    {
        // Aynı odada I. Grup (2.150) ve III. Grup (3.225) bir arada
        $quote = $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 1),
            [$this->guest('I'), $this->guest('III')],
        );

        $this->assertSame((2150.0 + 3225.0) * 6, $quote->total);
    }

    public function test_indirimsiz_devrede_bos_yatak_ucreti_alinir(): void
    {
        // Çolaklı 5. Devre (İndirimsiz), I. Grup → 2.500 TL; boş yatak 300 TL/gün
        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $this->period('colakli', 5),
            [$this->guest('I'), $this->guest('I'), $this->guest('I')],
        );

        $this->assertSame(1, $quote->emptyBedCount);
        $this->assertSame(1 * 300.0 * 6, $quote->emptyBedTotal);
        $this->assertSame(3 * 2500.0 * 6 + 1800.0, $quote->total);
    }

    public function test_indirimli_devrede_bos_yatak_ucreti_alinmaz(): void
    {
        // "İndirimli devrelerde boş kalan yataklar için ücret alınmaz." (Madde 8/9)
        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $this->period('colakli', 1),
            [$this->guest('I'), $this->guest('I')],
        );

        $this->assertSame(0.0, $quote->emptyBedTotal);
        $this->assertSame(2 * 2150.0 * 6, $quote->total);
    }

    public function test_gure_uc_kisilik_odada_iki_kisi_icin_bos_yatak_ucreti_alinmaz(): void
    {
        // "Güre Tesislerinde 3 kişilik odada 2 kişi konaklaması durumunda, kalan bir
        //  yatak için ücret alınmaz." (Madde 8/10)
        $quote = $this->quote(
            $this->room('gure', 'gure-3-kisilik'),
            $this->period('gure', 5),      // İndirimsiz, I. Grup → 2.400 TL
            [$this->guest('I'), $this->guest('I')],
        );

        $this->assertSame(0, $quote->emptyBedCount);
        $this->assertSame(0.0, $quote->emptyBedTotal);
        $this->assertSame(2 * 2400.0 * 6, $quote->total);
    }

    public function test_gure_dort_kisilik_odada_bos_yatak_ucreti_alinir(): void
    {
        $quote = $this->quote(
            $this->room('gure', 'gure-4-kisilik'),
            $this->period('gure', 5),
            [$this->guest('I'), $this->guest('I')],
        );

        $this->assertSame(2, $quote->emptyBedCount);
        $this->assertSame(2 * 300.0 * 6, $quote->emptyBedTotal);
    }

    // ---------------------------------------------------------------
    // Yaş kademeleri (Madde 8/5-6-7)
    // ---------------------------------------------------------------

    public function test_alti_on_bir_yas_cocuk_yuzde_altmis_ucretlendirilir(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $this->period('colakli', 1),
            [$this->guest('I'), $this->guest('I'), $this->guest('I', age: 8)],
        );

        $childUnit = 2150.0 * 0.60;

        $this->assertSame('child_6_11', $quote->guestLines[2]['age_category']);
        $this->assertSame($childUnit, $quote->guestLines[2]['unit_price']);
        $this->assertSame((2 * 2150.0 + $childUnit) * 6, $quote->total);
    }

    public function test_sifir_bes_yas_ucretsizdir_ve_yatak_isgal_etmez(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $this->period('colakli', 5),   // İndirimsiz → boş yatak ücreti var
            [$this->guest('I'), $this->guest('I'), $this->guest('I', age: 3)],
        );

        $this->assertSame('child_0_5', $quote->guestLines[2]['age_category']);
        $this->assertSame(0.0, $quote->guestLines[2]['line_total']);

        // Bebek yatak işgal etmediği için 4 kişilik odada 2 yatak boş sayılır.
        $this->assertSame(2, $quote->bedOccupants);
        $this->assertSame(2, $quote->emptyBedCount);
        $this->assertSame(2 * 2500.0 * 6 + 2 * 300.0 * 6, $quote->total);
    }

    public function test_sifir_bes_yas_yemek_talebinde_yuzde_kirk_ucretlendirilir(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $this->period('colakli', 1),
            [$this->guest('I'), $this->guest('I'), $this->guest('I', age: 3, wantsMeal: true)],
        );

        $mealUnit = 2150.0 * 0.40;

        $this->assertSame($mealUnit, $quote->guestLines[2]['unit_price']);
        $this->assertSame((2 * 2150.0 + $mealUnit) * 6, $quote->total);
    }

    public function test_yas_devre_baslangicina_gore_hesaplanir(): void
    {
        // Devre başlangıcı 17.05.2026; 16.05.2026'da 12 yaşını dolduran çocuk yetişkin sayılır.
        $period = $this->period('colakli', 1);

        $justTurnedTwelve = new GuestInput(
            customerGroupId: $this->groupId('I'),
            birthDate: Carbon::parse('2014-05-16'),
            name: 'Devre başında 12 yaşında',
        );

        $stillEleven = new GuestInput(
            customerGroupId: $this->groupId('I'),
            birthDate: Carbon::parse('2014-05-18'),
            name: 'Devre başında 11 yaşında',
        );

        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $period,
            [$this->guest('I'), $justTurnedTwelve, $stillEleven],
        );

        $this->assertSame('adult', $quote->guestLines[1]['age_category']);
        $this->assertSame('child_6_11', $quote->guestLines[2]['age_category']);
    }

    // ---------------------------------------------------------------
    // Zemin kat ve geç müracaat
    // ---------------------------------------------------------------

    public function test_zemin_kat_odasinda_yuzde_on_indirim_uygulanir(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-2-kisilik-zemin'),
            $this->period('colakli', 1),
            [$this->guest('I'), $this->guest('I')],
        );

        $this->assertSame(2150.0 * 0.90, $quote->guestLines[0]['unit_price']);
        $this->assertSame(2 * 1935.0 * 6, $quote->total);
    }

    public function test_gec_muracaatta_kisi_basi_gunluk_ilave_ucret_eklenir(): void
    {
        // 01.07.2026 sonrası müracaatlarda kişi başı günlük +300 TL
        $quote = $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 21),   // İndirimli, I. Grup → 2.300 TL
            [$this->guest('I'), $this->guest('I')],
            applicationDate: Carbon::parse('2026-08-13'),
        );

        $this->assertSame(300.0, $quote->surchargePerPersonDay);
        $this->assertSame(2600.0, $quote->guestLines[0]['unit_price']);
        $this->assertSame(2 * 2600.0 * 6, $quote->total);
    }

    public function test_nisan_haziran_muracaatinda_iki_yuz_lira_ilave_alinir(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 12),
            [$this->guest('I')],
            applicationDate: Carbon::parse('2026-05-10'),
        );

        $this->assertSame(200.0, $quote->surchargePerPersonDay);
        $this->assertSame(2700.0, $quote->guestLines[0]['unit_price']); // 2.500 + 200
    }

    public function test_ilave_ucret_sifir_bes_yas_grubuna_uygulanmaz(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-4-kisilik'),
            $this->period('colakli', 12),
            [$this->guest('I'), $this->guest('I', age: 3)],
            applicationDate: Carbon::parse('2026-08-13'),
        );

        $this->assertSame(2800.0, $quote->guestLines[0]['unit_price']); // 2.500 + 300
        $this->assertSame(0.0, $quote->guestLines[1]['unit_price']);
    }

    // ---------------------------------------------------------------
    // Tablo 2 — Çolaklı villaları
    // ---------------------------------------------------------------

    public function test_villa_tablo_iki_ucretlerinden_hesaplanir(): void
    {
        // Villa 4-20. Devreler, I. Grup → 12 yaş üstü 1.680 TL
        $quote = $this->quote(
            $this->room('colakli', 'colakli-villa'),
            $this->period('colakli', 5),
            array_fill(0, 5, $this->guest('I')),
        );

        $this->assertSame(1680.0, $quote->guestLines[0]['unit_price']);
        $this->assertSame(5 * 1680.0 * 6, $quote->total);
        $this->assertSame(0.0, $quote->villaMinimumAdjustment);
    }

    public function test_villada_bes_kisiden_az_konaklamada_asgari_gunluk_tutar_uygulanir(): void
    {
        // "Üç oda ve beş yataktan oluşan villalar, en az beş kişi üzerinden ücretlendirilir." (Madde 8/3)
        // Tablo 2, I. Grup, 4-20. Devreler → En düşük Günlük Ücret 8.400 TL
        $quote = $this->quote(
            $this->room('colakli', 'colakli-villa'),
            $this->period('colakli', 5),
            array_fill(0, 3, $this->guest('I')),
        );

        $this->assertSame(8400.0 * 6, $quote->total);
        $this->assertSame((8400.0 - 3 * 1680.0) * 6, $quote->villaMinimumAdjustment);
    }

    public function test_villada_alti_kisi_asgari_tutarin_uzerinde_ucretlendirilir(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-villa'),
            $this->period('colakli', 5),
            array_fill(0, 6, $this->guest('I')),
        );

        $this->assertSame(6 * 1680.0 * 6, $quote->total);
        $this->assertSame(0.0, $quote->villaMinimumAdjustment);
    }

    public function test_villada_cocuk_ucreti_tablodan_okunur(): void
    {
        // Tablo 2, I. Grup, 4-20. Devreler → 6-12 yaş 840 TL (yüzde hesabı değil, tablo değeri)
        $quote = $this->quote(
            $this->room('colakli', 'colakli-villa'),
            $this->period('colakli', 5),
            [...array_fill(0, 5, $this->guest('I')), $this->guest('I', age: 8)],
        );

        $this->assertSame(840.0, $quote->guestLines[5]['unit_price']);
    }

    public function test_villada_bos_yatak_ucreti_alinmaz(): void
    {
        $quote = $this->quote(
            $this->room('colakli', 'colakli-villa'),
            $this->period('colakli', 5),
            array_fill(0, 2, $this->guest('I')),
        );

        $this->assertSame(0, $quote->emptyBedCount);
        $this->assertSame(0.0, $quote->emptyBedTotal);
    }

    // ---------------------------------------------------------------
    // Birleşen devreler (Madde 5/7)
    // ---------------------------------------------------------------

    public function test_iki_ardisik_devre_on_uc_gece_olarak_ucretlendirilir(): void
    {
        // Çolaklı 3. Devre (indirimli, 2.150) + 4. Devre (indirimsiz, 2.500)
        $quote = $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 3),
            [$this->guest('I'), $this->guest('I')],
            second: $this->period('colakli', 4),
        );

        $this->assertSame(13, $quote->nights);
        $this->assertCount(2, $quote->segments);

        // İlk devre 6 gece kendi tarifesinden, köprü gecesiyle birlikte ikinci devre 7 gece.
        $this->assertSame(6, $quote->segments[0]['nights']);
        $this->assertSame(7, $quote->segments[1]['nights']);

        $expected = 2 * 2150.0 * 6 + 2 * 2500.0 * 7;
        $this->assertSame($expected, $quote->total);
    }

    public function test_birlestirilemeyen_devreler_reddedilir(): void
    {
        // 2. ve 3. Devre farklı birleşim gruplarında ((1-2) ve (3-4))
        $this->expectException(RuntimeException::class);

        $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 2),
            [$this->guest('I')],
            second: $this->period('colakli', 3),
        );
    }

    public function test_ardisik_olmayan_devreler_reddedilir(): void
    {
        $this->expectException(RuntimeException::class);

        $this->quote(
            $this->room('colakli', 'colakli-2-kisilik'),
            $this->period('colakli', 1),
            [$this->guest('I')],
            second: $this->period('colakli', 5),
        );
    }

    // ---------------------------------------------------------------
    // Peşinat (Madde 5/8)
    // ---------------------------------------------------------------

    public function test_pesinat_kademeleri(): void
    {
        $room = $this->room('colakli', 'colakli-2-kisilik');
        $first = $this->period('colakli', 1);
        $second = $this->period('colakli', 2);

        // Bir devre, birden fazla kişi → 10.000 TL
        $this->assertSame(10000.0, $this->quote($room, $first, [$this->guest('I'), $this->guest('I')])->depositAmount);

        // İki devre, birden fazla kişi → 20.000 TL
        $this->assertSame(20000.0, $this->quote($room, $first, [$this->guest('I'), $this->guest('I')], second: $second)->depositAmount);

        // Tek kişi konaklama → 5.000 TL / 10.000 TL
        $this->assertSame(5000.0, $this->quote($room, $first, [$this->guest('I')])->depositAmount);
        $this->assertSame(10000.0, $this->quote($room, $first, [$this->guest('I')], second: $second)->depositAmount);
    }

    // ---------------------------------------------------------------
    // Bakiye vadesi (Madde 8/8)
    // ---------------------------------------------------------------

    public function test_bakiye_vadesi_on_bes_gundur(): void
    {
        $due = $this->pricer->balanceDueDate(Carbon::parse('2026-04-01'), Carbon::parse('2026-07-05'));

        $this->assertSame('2026-04-16', $due->toDateString());
    }

    public function test_bakiye_vadesi_devre_baslangicini_asamaz(): void
    {
        $due = $this->pricer->balanceDueDate(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-05'));

        $this->assertSame('2026-07-05', $due->toDateString());
    }
}
