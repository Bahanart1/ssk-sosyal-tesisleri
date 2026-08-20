<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomPeriodOccupancy;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Camp2026Seeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fiziksel oda ataması: bir oda yalnızca kendi devresinde doludur; aynı oda
 * başka bir devrede başka üyeye verilebilir.
 */
class RoomAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RoomType $roomType;

    private Period $on5;

    private Period $on6;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-13');

        $this->seed([
            SettingSeeder::class,
            CustomerGroupSeeder::class,
            FacilitySeeder::class,
            Camp2026Seeder::class,
            DemoUserSeeder::class,
        ]);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->roomType = RoomType::where('code', 'colakli-2-kisilik')->firstOrFail();
        $this->on5 = Period::where('number', 15)->firstOrFail();
        $this->on6 = Period::where('number', 16)->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bos_oda_devre_bazinda_hesaplanir(): void
    {
        $room = $this->makeRoom('A', '101');

        // 15. devrede dolu
        $this->makeReservation($this->on5)->update(['status' => 'approved', 'room_id' => $room->id]);

        $this->assertFalse(
            Room::whereKey($room->id)->freeForPeriods([$this->on5->id])->exists(),
            'Kendi devresinde dolu görünmeli'
        );

        $this->assertTrue(
            Room::whereKey($room->id)->freeForPeriods([$this->on6->id])->exists(),
            'Başka devrede boş görünmeli'
        );
    }

    public function test_birlesik_devre_odayi_her_iki_devrede_de_isgal_eder(): void
    {
        $room = $this->makeRoom('A', '102');

        $this->makeReservation($this->on5)->update([
            'status' => 'approved',
            'room_id' => $room->id,
            'second_period_id' => $this->on6->id,
            'end_date' => $this->on6->end_date,
            'nights' => 13,
        ]);

        $this->assertFalse(Room::whereKey($room->id)->freeForPeriods([$this->on5->id])->exists());
        $this->assertFalse(Room::whereKey($room->id)->freeForPeriods([$this->on6->id])->exists());
    }

    public function test_iptal_ve_red_odayi_serbest_birakir(): void
    {
        $room = $this->makeRoom('A', '103');

        $reservation = $this->makeReservation($this->on5);
        $reservation->update(['status' => 'approved', 'room_id' => $room->id]);
        $this->assertFalse(Room::whereKey($room->id)->freeForPeriods([$this->on5->id])->exists());

        $reservation->update(['status' => 'cancelled']);
        $this->assertTrue(Room::whereKey($room->id)->freeForPeriods([$this->on5->id])->exists());
    }

    public function test_pasif_oda_atama_listesinde_cikmaz(): void
    {
        $this->makeRoom('A', '104');
        $pasif = $this->makeRoom('A', '105');
        $pasif->update(['is_active' => false]);

        $reservation = $this->makeReservation($this->on5);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk();

        $adaylar = $response->viewData('availableRooms')->flatten()->pluck('number');

        $this->assertTrue($adaylar->contains('104'));
        $this->assertFalse($adaylar->contains('105'), 'Pasif oda atanamaz');
    }

    public function test_baskasina_verilmis_oda_atama_listesinde_cikmaz(): void
    {
        $dolu = $this->makeRoom('A', '106');
        $this->makeRoom('A', '107');

        $this->makeReservation($this->on5)->update(['status' => 'approved', 'room_id' => $dolu->id]);

        $yeni = $this->makeReservation($this->on5);

        $adaylar = $this->actingAs($this->admin)
            ->get(route('admin.reservations.show', $yeni))
            ->assertOk()
            ->viewData('availableRooms')->flatten()->pluck('number');

        $this->assertFalse($adaylar->contains('106'));
        $this->assertTrue($adaylar->contains('107'));
    }

    public function test_yonetici_oda_atayabilir_ve_kaldirabilir(): void
    {
        $room = $this->makeRoom('B', '201');
        $reservation = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), ['room_id' => $room->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($room->id, $reservation->fresh()->room_id);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), ['room_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($reservation->fresh()->room_id);
    }

    public function test_odemesi_tamamlanmis_basvuruya_da_oda_atanabilir(): void
    {
        $room = $this->makeRoom('B', '203');
        $reservation = $this->makeReservation($this->on5);
        $reservation->update(['status' => 'paid']);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), ['room_id' => $room->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($room->id, $reservation->fresh()->room_id);
    }

    public function test_iptal_edilmis_basvuruya_oda_atanamaz(): void
    {
        $room = $this->makeRoom('B', '204');
        $reservation = $this->makeReservation($this->on5);
        $reservation->update(['status' => 'cancelled']);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), ['room_id' => $room->id])
            ->assertStatus(422);
    }

    public function test_dolu_oda_atanamaz(): void
    {
        $room = $this->makeRoom('B', '202');
        $this->makeReservation($this->on5)->update(['status' => 'approved', 'room_id' => $room->id]);

        $yeni = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $yeni), ['room_id' => $room->id])
            ->assertSessionHasErrors('room_id');

        $this->assertNull($yeni->fresh()->room_id);
    }

    public function test_baska_tesisin_odasi_atanamaz(): void
    {
        $gure = Facility::where('slug', 'gure')->firstOrFail();
        $gureType = RoomType::where('facility_id', $gure->id)->where('kind', 'room')->firstOrFail();

        $yabanci = Room::create([
            'facility_id' => $gure->id,
            'room_type_id' => $gureType->id,
            'block' => 'G',
            'number' => '1',
            'is_active' => true,
        ]);

        $reservation = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), ['room_id' => $yabanci->id])
            ->assertSessionHasErrors('room_id');
    }

    public function test_oda_envanteri_devreye_gore_dolulugu_gosterir(): void
    {
        $dolu = $this->makeRoom('MENEKŞE', '301');
        $this->makeRoom('MENEKŞE', '302');

        $reservation = $this->makeReservation($this->on5);
        $reservation->update(['status' => 'approved', 'room_id' => $dolu->id]);

        $facility = $this->roomType->facility;

        // Devre seçilince dolu oda, sahibinin adıyla görünür
        $this->actingAs($this->admin)
            ->get(route('admin.rooms.index', ['tesis' => $facility->slug, 'devre' => $this->on5->id]))
            ->assertOk()
            ->assertSee($reservation->user->name)
            ->assertSee('Boş');

        // Başka devrede aynı oda boştur
        $response = $this->actingAs($this->admin)
            ->get(route('admin.rooms.index', ['tesis' => $facility->slug, 'devre' => $this->on6->id]))
            ->assertOk();

        $this->assertTrue($response->viewData('occupancy')->isEmpty());
    }

    public function test_devre_detayinda_atanmamis_basvuru_uyarisi_cikar(): void
    {
        $this->makeReservation($this->on5)->update(['status' => 'approved']);

        $this->actingAs($this->admin)
            ->get(route('admin.periods.show', $this->on5))
            ->assertOk()
            ->assertSee('1 başvuruya henüz fiziksel oda atanmadı.');
    }

    public function test_basvuru_listesi_satir_ici_atama_secenegi_sunar(): void
    {
        $this->makeRoom('C', '401');
        $this->makeRoom('C', '402');

        $odenmis = $this->makeReservation($this->on5);
        $odenmis->update(['status' => 'paid']);

        $iptal = $this->makeReservation($this->on5);
        $iptal->update(['status' => 'cancelled']);

        $secenekler = $this->actingAs($this->admin)
            ->get(route('admin.reservations.index'))
            ->assertOk()
            ->viewData('roomOptions');

        $this->assertArrayHasKey($odenmis->id, $secenekler, 'Ödenmiş başvuruya oda atanabilmeli');
        $this->assertSame(2, $secenekler[$odenmis->id]->flatten()->count());
        $this->assertArrayNotHasKey($iptal->id, $secenekler, 'İptal edilene oda atanamaz');
    }

    public function test_liste_dolu_odayi_baska_basvuruya_onermez(): void
    {
        $dolu = $this->makeRoom('C', '403');
        $this->makeRoom('C', '404');

        $sahibi = $this->makeReservation($this->on5);
        $sahibi->update(['status' => 'paid', 'room_id' => $dolu->id]);

        $digeri = $this->makeReservation($this->on5);
        $digeri->update(['status' => 'paid']);

        $secenekler = $this->actingAs($this->admin)
            ->get(route('admin.reservations.index'))
            ->assertOk()
            ->viewData('roomOptions');

        // Odayı tutan başvuru kendi odasını listede görmeye devam eder
        $this->assertTrue($secenekler[$sahibi->id]->flatten()->contains('number', '403'));
        $this->assertFalse($secenekler[$digeri->id]->flatten()->contains('number', '403'));
        $this->assertTrue($secenekler[$digeri->id]->flatten()->contains('number', '404'));
    }

    public function test_basvurular_oda_atamasina_gore_suzulur(): void
    {
        $room = $this->makeRoom('C', '405');

        $atanmis = $this->makeReservation($this->on5);
        $atanmis->update(['status' => 'paid', 'room_id' => $room->id]);

        $atanmamis = $this->makeReservation($this->on5);
        $atanmamis->update(['status' => 'paid']);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['status' => 'paid', 'room' => 'unassigned']))
            ->assertOk()
            ->assertSee($atanmamis->code)
            ->assertDontSee($atanmis->code);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['status' => 'paid', 'room' => 'assigned']))
            ->assertOk()
            ->assertSee($atanmis->code)
            ->assertDontSee($atanmamis->code);
    }

    public function test_detay_ekrani_oda_tipinin_bloklarini_ve_alternatifini_soyler(): void
    {
        // Aynı yatak sayısında ikinci bir tip (zemin kat) — atama listesine karışmamalı
        $zemin = RoomType::where('facility_id', $this->roomType->facility_id)
            ->where('bed_count', $this->roomType->bed_count)
            ->where('is_ground_floor', true)
            ->firstOrFail();

        $this->makeRoom('BEGONYA', '1');
        $this->makeRoom('İDARE ÜSTÜ', '1');

        Room::create([
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $zemin->id,
            'block' => 'LALE ZEMİN', 'number' => '1', 'is_active' => true,
        ]);

        $reservation = $this->makeReservation($this->on5);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk();

        $this->assertSame(['BEGONYA', 'İDARE ÜSTÜ'], $response->viewData('roomTypeBlocks'));
        $this->assertTrue($response->viewData('alternateTypes')->contains('id', $zemin->id));

        // Zemin kat odası farklı tip olduğu için seçeneklerde çıkmaz
        $this->assertFalse(
            $response->viewData('availableRooms')->flatten()->contains('block', 'LALE ZEMİN')
        );
    }

    public function test_yonetici_ikinci_oda_tahsis_edebilir(): void
    {
        $a = $this->makeRoom('D', '1');
        $b = $this->makeRoom('D', '2');
        $reservation = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), [
                'room_id' => $a->id,
                'second_room_id' => $b->id,
            ])
            ->assertSessionHasNoErrors();

        $reservation->refresh();

        $this->assertSame($a->id, $reservation->room_id);
        $this->assertSame($b->id, $reservation->second_room_id);
        $this->assertSame($this->roomType->capacity() * 2, $reservation->allocatedCapacity());
    }

    public function test_ikinci_oda_da_dolu_sayilir(): void
    {
        $a = $this->makeRoom('D', '3');
        $b = $this->makeRoom('D', '4');

        $this->makeReservation($this->on5)->update([
            'status' => 'approved', 'room_id' => $a->id, 'second_room_id' => $b->id,
        ]);

        $this->assertFalse(Room::whereKey($b->id)->freeForPeriods([$this->on5->id])->exists(),
            'İkinci oda da işgal edilmiş sayılmalı');
        $this->assertTrue(Room::whereKey($b->id)->freeForPeriods([$this->on6->id])->exists());
    }

    public function test_ayni_oda_iki_kez_verilemez(): void
    {
        $a = $this->makeRoom('D', '5');
        $reservation = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), [
                'room_id' => $a->id,
                'second_room_id' => $a->id,
            ])
            ->assertSessionHasErrors('second_room_id');
    }

    public function test_ikinci_oda_baskasina_verilmisse_reddedilir(): void
    {
        $a = $this->makeRoom('D', '6');
        $dolu = $this->makeRoom('D', '7');

        $this->makeReservation($this->on5)->update(['status' => 'approved', 'room_id' => $dolu->id]);

        $yeni = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $yeni), [
                'room_id' => $a->id,
                'second_room_id' => $dolu->id,
            ])
            ->assertSessionHasErrors('second_room_id');

        $this->assertNull($yeni->fresh()->room_id, 'Hatalı istek hiçbir şey kaydetmemeli');
    }

    /**
     * İki yönetici aynı odayı aynı devrede iki başvuruya tahsis etmeye çalışırsa
     * ikincisi reddedilmelidir. Uygunluk kontrolü ile yazma arasındaki boşlukta
     * odanın kapılmış olması bu senaryonun ta kendisidir.
     */
    public function test_bayat_sayfadan_gelen_tahsis_reddedilir(): void
    {
        $room = $this->makeRoom('B', '210');
        $ilk = $this->makeReservation($this->on5);
        $ikinci = $this->makeReservation($this->on5);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $ilk), ['room_id' => $room->id])
            ->assertSessionHasNoErrors();

        // İkinci yöneticinin ekranı, oda henüz boşken yüklenmişti.
        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $ikinci), ['room_id' => $room->id])
            ->assertSessionHasErrors('room_id');

        $this->assertSame($room->id, $ilk->fresh()->room_id);
        $this->assertNull($ikinci->fresh()->room_id, 'Oda ikinci başvuruya yazılmamalı');
    }

    /**
     * Uygulama katmanı atlansa dahi veritabanı aynı oda/devre çiftini iki kez
     * kabul etmemelidir; SQLite'ta okuma kilidi olmadığı için asıl güvence budur.
     */
    public function test_veritabani_ayni_oda_ve_devreyi_iki_basvuruya_baglamaz(): void
    {
        $room = $this->makeRoom('B', '211');
        $ilk = $this->makeReservation($this->on5);
        $ikinci = $this->makeReservation($this->on5);

        $ilk->update(['room_id' => $room->id]);

        $this->expectException(QueryException::class);

        $ikinci->update(['room_id' => $room->id]);
    }

    /** Kısmi indeks yalnızca odayı işgal eden durumları kapsar. */
    public function test_iptal_edilen_basvuru_odayi_indekste_de_serbest_birakir(): void
    {
        $room = $this->makeRoom('B', '212');
        $ilk = $this->makeReservation($this->on5);
        $ikinci = $this->makeReservation($this->on5);

        $ilk->update(['room_id' => $room->id]);
        $ilk->update(['status' => 'cancelled']);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $ikinci), ['room_id' => $room->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($room->id, $ikinci->fresh()->room_id);
    }

    /**
     * Eski kısmi indeksin yakalayamadığı çapraz çakışma: bir başvurunun birinci
     * odası ile başka bir başvurunun ikinci odası aynı devrede aynı oda olamaz.
     * Doluluk normalize edildiği için artık veritabanı reddediyor.
     */
    public function test_capraz_oda_cakismasi_engellenir(): void
    {
        $oda = $this->makeRoom('D', '301');
        $digerOda = $this->makeRoom('D', '302');

        $ilk = $this->makeReservation($this->on5);
        $ilk->update(['room_id' => $oda->id]);

        $ikinci = $this->makeReservation($this->on5);
        $ikinci->update(['room_id' => $digerOda->id]);

        // İkinci başvuru, birincinin odasını "ikinci oda" olarak almaya çalışıyor.
        $this->expectException(QueryException::class);

        $ikinci->update(['second_room_id' => $oda->id]);
    }

    /** Doluluk satırları başvurunun durumunu izler. */
    public function test_doluluk_satirlari_durumu_izler(): void
    {
        $oda = $this->makeRoom('D', '303');
        $reservation = $this->makeReservation($this->on5);

        $reservation->update(['room_id' => $oda->id]);
        $this->assertSame(1, $reservation->occupancies()->count());

        $reservation->update(['status' => 'cancelled']);
        $this->assertSame(0, $reservation->occupancies()->count(), 'İptal odayı serbest bırakmalı');

        $reservation->update(['status' => 'approved']);
        $this->assertSame(1, $reservation->occupancies()->count(), 'Geri alınınca yeniden işgal etmeli');

        $reservation->delete();
        $this->assertSame(0, RoomPeriodOccupancy::where('room_id', $oda->id)->count());
    }

    /** Birleşik devre başvurusu her iki devrede de yer kaplar. */
    public function test_birlesik_devre_iki_satir_uretir(): void
    {
        $oda = $this->makeRoom('D', '304');
        $reservation = $this->makeReservation($this->on5);

        $reservation->update(['room_id' => $oda->id, 'second_period_id' => $this->on6->id]);

        $this->assertEqualsCanonicalizing(
            [$this->on5->id, $this->on6->id],
            $reservation->occupancies()->pluck('period_id')->all()
        );
    }

    private function makeRoom(string $block, string $number): Room
    {
        return Room::create([
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $this->roomType->id,
            'block' => $block,
            'number' => $number,
            'is_active' => true,
        ]);
    }

    private function makeReservation(Period $period): Reservation
    {
        $user = User::create([
            'name' => 'Üye '.(User::count() + 1),
            'tc_no' => str_pad((string) (10000000000 + User::count()), 11, '0', STR_PAD_LEFT),
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'),
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'code' => '2026-'.str_pad((string) (Reservation::count() + 1), 6, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $this->roomType->id,
            'period_id' => $period->id,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'nights' => 6,
            'status' => 'pending',
            'application_date' => now()->toDateString(),
            'total_price' => 33600,
            'deposit_amount' => 10000,
        ]);

        $reservation->guests()->create([
            'full_name' => $user->name,
            'tc_no' => $user->tc_no,
            'birth_date' => '1985-01-01',
            'relation' => 'self',
            'customer_group_id' => $user->customer_group_id,
            'age_category' => 'adult',
            'sort_order' => 0,
        ]);

        return $reservation->fresh();
    }
}
