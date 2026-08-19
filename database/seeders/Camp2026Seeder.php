<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Tariff;
use App\Models\TariffPrice;
use Illuminate\Database\Seeder;

/**
 * 2026 yılı devreleri ve ücret tabloları.
 *
 * Kaynak: sigortader.com.tr — "2026 YILI ÇOLAKLI VE GÜRE TESİSLERİ DÖNEM TARİHLERİ VE
 * ÜCRETLENDİRME LİSTESİ". Tablo 1 oda ücretleri, Tablo 2 Çolaklı villa ücretleridir.
 *
 * Villa tablosundaki indirimli devre grubu oda tablosundan farklıdır (villa: 1-2-3,
 * oda: 1 ve 3), bu nedenle her devre ayrı ayrı oda ve villa tarifesine bağlanır.
 */
class Camp2026Seeder extends Seeder
{
    private const YEAR = 2026;

    public function run(): void
    {
        $groups = CustomerGroup::pluck('id', 'code');
        $colakli = Facility::where('slug', 'colakli')->firstOrFail();
        $gure = Facility::where('slug', 'gure')->firstOrFail();

        // ---------------------------------------------------------------
        // TABLO 1 — Kişi başı günlük oda ücretleri (vergiler dahil)
        // ---------------------------------------------------------------
        $colakliRoomDiscountedEarly = $this->tariff($colakli, 'Çolaklı · 1 ve 3. Devreler (İndirimli)', 'room', true, null, 1, [
            'I' => 2150, 'II' => 2700, 'III' => 3225,
        ], $groups);

        $colakliRoomStandard = $this->tariff($colakli, 'Çolaklı · 2 ve 4-20. Devreler (İndirimsiz)', 'room', false, 300, 2, [
            'I' => 2500, 'II' => 3125, 'III' => 3750,
        ], $groups);

        $colakliRoomDiscountedLate = $this->tariff($colakli, 'Çolaklı · 21-22-23. Devreler (İndirimli)', 'room', true, null, 3, [
            'I' => 2300, 'II' => 2875, 'III' => 3450,
        ], $groups);

        $gureRoomDiscountedEarly = $this->tariff($gure, 'Güre · 1-2. Devreler (İndirimli)', 'room', true, null, 1, [
            'I' => 2050, 'II' => 2565, 'III' => 3075,
        ], $groups);

        $gureRoomStandard = $this->tariff($gure, 'Güre · 3-13. Devreler (İndirimsiz)', 'room', false, 300, 2, [
            'I' => 2400, 'II' => 3000, 'III' => 3600,
        ], $groups);

        $gureRoomDiscountedLate = $this->tariff($gure, 'Güre · 14-15. Devreler (İndirimli)', 'room', true, null, 3, [
            'I' => 2200, 'II' => 2750, 'III' => 3300,
        ], $groups);

        // ---------------------------------------------------------------
        // TABLO 2 — Çolaklı villaları, yemeksiz günlük kişi başı ücretler
        // [12 yaş üstü, 6-12 yaş, en düşük günlük ücret]
        // ---------------------------------------------------------------
        $villaDiscountedEarly = $this->tariff($colakli, 'Çolaklı Villa · 1-2-3. Devreler (İndirimli)', 'villa', true, null, 4, [
            'I' => [1275, 640, 6375], 'II' => [1765, 880, 8825], 'III' => [2255, 1130, 11275],
        ], $groups);

        $villaStandard = $this->tariff($colakli, 'Çolaklı Villa · 4-20. Devreler (İndirimsiz)', 'villa', false, null, 5, [
            'I' => [1680, 840, 8400], 'II' => [2100, 1050, 10500], 'III' => [2600, 1300, 13000],
        ], $groups);

        $villaDiscountedLate = $this->tariff($colakli, 'Çolaklı Villa · 21-22-23. Devreler (İndirimli)', 'villa', true, null, 6, [
            'I' => [1540, 770, 7700], 'II' => [1960, 980, 9800], 'III' => [2450, 1225, 12250],
        ], $groups);

        // ---------------------------------------------------------------
        // ÇOLAKLI DEVRELERİ — 23 devre, 17 Mayıs – 24 Ekim 2026
        // İndirimli: 1, 3, 21, 22, 23
        // Birleşen devreler: (1-2)(3-4)(5-6)(7-8)(9-10)(11-12)(13-14)(15-16)(17-18)(19-20)(21-22-23)
        // ---------------------------------------------------------------
        $colakliStarts = [
            1 => '2026-05-17',  2 => '2026-05-24',  3 => '2026-05-31',  4 => '2026-06-07',
            5 => '2026-06-14',  6 => '2026-06-21',  7 => '2026-06-28',  8 => '2026-07-05',
            9 => '2026-07-12', 10 => '2026-07-19', 11 => '2026-07-26', 12 => '2026-08-02',
            13 => '2026-08-09', 14 => '2026-08-16', 15 => '2026-08-23', 16 => '2026-08-30',
            17 => '2026-09-06', 18 => '2026-09-13', 19 => '2026-09-20', 20 => '2026-09-27',
            21 => '2026-10-04', 22 => '2026-10-11', 23 => '2026-10-18',
        ];

        foreach ($colakliStarts as $no => $start) {
            $discounted = in_array($no, [1, 3, 21, 22, 23], true);

            $roomTariff = match (true) {
                in_array($no, [1, 3], true) => $colakliRoomDiscountedEarly,
                $no >= 21 => $colakliRoomDiscountedLate,
                default => $colakliRoomStandard,
            };

            $villaTariff = match (true) {
                $no <= 3 => $villaDiscountedEarly,
                $no >= 21 => $villaDiscountedLate,
                default => $villaStandard,
            };

            $this->period($colakli, $no, $start, $discounted, $this->pairGroup($no, 21), $roomTariff, $villaTariff,
                $no === 2 ? 'Kurban Bayramı haftası olması nedeniyle indirimli olarak değerlendirilmez.' : null);
        }

        // ---------------------------------------------------------------
        // GÜRE DEVRELERİ — 15 devre, 14 Haziran – 26 Eylül 2026
        // İndirimli: 1, 2, 14, 15
        // Birleşen devreler: (1-2)(3-4)(5-6)(7-8)(9-10)(11-12)(13-14-15)
        // ---------------------------------------------------------------
        $gureStarts = [
            1 => '2026-06-14',  2 => '2026-06-21',  3 => '2026-06-28',  4 => '2026-07-05',
            5 => '2026-07-12',  6 => '2026-07-19',  7 => '2026-07-26',  8 => '2026-08-02',
            9 => '2026-08-09', 10 => '2026-08-16', 11 => '2026-08-23', 12 => '2026-08-30',
            13 => '2026-09-06', 14 => '2026-09-13', 15 => '2026-09-20',
        ];

        foreach ($gureStarts as $no => $start) {
            $discounted = in_array($no, [1, 2, 14, 15], true);

            $roomTariff = match (true) {
                $no <= 2 => $gureRoomDiscountedEarly,
                $no >= 14 => $gureRoomDiscountedLate,
                default => $gureRoomStandard,
            };

            $this->period($gure, $no, $start, $discounted, $this->pairGroup($no, 13), $roomTariff, null);
        }
    
        $this->linkCombinablePeriods();
    }

    /**
     * Birleşen devre grubu: ardışık ikişerli gruplar, son grup üçlü.
     * $tripleFrom, üçlü grubun başladığı devre numarasıdır (Çolaklı 21, Güre 13).
     */
    private function pairGroup(int $number, int $tripleFrom): int
    {
        return $number >= $tripleFrom
            ? intdiv($tripleFrom - 1, 2) + 1
            : intdiv($number - 1, 2) + 1;
    }

    /**
     * @param  array<string, int|array{0:int,1:int,2:int}>  $prices
     */
    private function tariff(
        Facility $facility,
        string $name,
        string $scope,
        bool $isDiscounted,
        ?float $emptyBedFee,
        int $sortOrder,
        array $prices,
        \Illuminate\Support\Collection $groups,
    ): Tariff {
        $tariff = Tariff::updateOrCreate(
            ['facility_id' => $facility->id, 'year' => self::YEAR, 'name' => $name],
            [
                'scope' => $scope,
                'is_discounted' => $isDiscounted,
                'empty_bed_fee' => $emptyBedFee,
                'sort_order' => $sortOrder,
            ]
        );

        foreach ($prices as $code => $value) {
            [$adult, $child, $minDaily] = is_array($value)
                ? $value
                : [$value, null, null];

            TariffPrice::updateOrCreate(
                ['tariff_id' => $tariff->id, 'customer_group_id' => $groups[$code]],
                ['adult_price' => $adult, 'child_price' => $child, 'min_daily_total' => $minDaily]
            );
        }

        return $tariff->load('prices');
    }

    private function period(
        Facility $facility,
        int $number,
        string $start,
        bool $isDiscounted,
        int $combineGroup,
        Tariff $roomTariff,
        ?Tariff $villaTariff,
        ?string $note = null,
    ): Period {
        $startDate = \Carbon\Carbon::parse($start);

        return Period::updateOrCreate(
            ['facility_id' => $facility->id, 'year' => self::YEAR, 'number' => $number],
            [
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addDays(6), // Pazar giriş → takip eden Cumartesi çıkış
                'nights' => 6,
                'is_discounted' => $isDiscounted,
                'combine_group' => $combineGroup,
                'room_tariff_id' => $roomTariff->id,
                'villa_tariff_id' => $villaTariff?->id,
                'is_open' => true,
                'note' => $note,
            ]
        );
    }

    /**
     * Birleşebilen devre eşleşmelerini kurar: aynı grup içindeki ardışık
     * devreler birbirine bağlanır. Yönetici bunu sonradan Devre Ayarları'ndan
     * değiştirebilir.
     */
    private function linkCombinablePeriods(): void
    {
        Period::query()->whereNotNull('combine_group')->get()
            ->groupBy(fn (Period $p) => $p->facility_id . '-' . $p->year . '-' . $p->combine_group)
            ->each(function ($grup) {
                $sirali = $grup->sortBy('number')->values();

                foreach ($sirali as $i => $period) {
                    $sonraki = $sirali[$i + 1] ?? null;

                    $period->forceFill([
                        'combines_with_id' => ($sonraki && $sonraki->number === $period->number + 1)
                            ? $sonraki->id
                            : null,
                    ])->save();
                }
            });
    }
}
