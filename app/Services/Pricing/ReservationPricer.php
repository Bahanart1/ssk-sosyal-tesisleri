<?php

namespace App\Services\Pricing;

use App\Models\Period;
use App\Models\ReservationGuest;
use App\Models\Setting;
use App\Models\Tariff;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Konaklama ücreti hesaplayıcısı — tek doğruluk kaynağı.
 *
 * Hem müşteri başvuru sihirbazının canlı özeti, hem adminin düzenleme ekranı,
 * hem de kayıt anındaki tutar bu sınıftan geçer.
 *
 * Kaynak kurallar: sigortader.com.tr "2026 Yılı Kamp Dönemleri ve Ücretleri" (Tablo 1, Tablo 2)
 * ve "Kamp Konaklama Usul ve Esasları" (Madde 8).
 *
 * Birim ücret hesabı sırası:
 *   1. Tablo ücreti (tesis × devre bandı × müşteri grubu)
 *   2. Zemin kat indirimi (Çolaklı iki kişilik zemin kat odaları, %10)
 *   3. Yaş katsayısı (12+ tam · 6-11 %60 · 0-5 ücretsiz, yemek talebinde %40)
 *   4. Müracaat tarihine göre kişi başı günlük ilave ücret (yalnız ücretli kişilere)
 */
class ReservationPricer
{
    public function quote(PricingInput $input): PriceBreakdown
    {
        $periods = $input->periods();

        if (count($periods) === 2 && ! $periods[0]->canCombineWith($periods[1])) {
            throw new RuntimeException('Seçilen iki devre birleştirilemez.');
        }

        if ($input->guests === []) {
            throw new RuntimeException('En az bir kişi belirtilmelidir.');
        }

        $roomType = $input->roomType;
        $periodStart = $input->startDate();
        $surcharge = $input->surchargeOverride ?? $this->surchargeFor($input->applicationDate);

        // Kişilerin yaş grubu, devre başlangıcına göre bir kez belirlenir (Madde 8/7).
        $categories = array_map(
            fn (GuestInput $g) => ReservationGuest::categoryFor($g->birthDate, $periodStart),
            $input->guests
        );

        $bedOccupants = count(array_filter($categories, fn ($c) => $c !== 'child_0_5'));
        $billedPersons = count(array_filter(
            $categories,
            fn ($c, $i) => $c !== 'child_0_5' || $input->guests[$i]->wantsMeal,
            ARRAY_FILTER_USE_BOTH
        ));

        $segments = [];
        $guestTotals = array_fill(0, count($input->guests), 0.0);
        $emptyBedTotal = 0.0;
        $villaMinimumAdjustment = 0.0;
        $emptyBedCount = 0;
        $emptyBedFeePerDay = 0.0;

        foreach ($this->nightAllocation($periods) as $index => [$period, $nights]) {
            $tariff = $period->tariffFor($roomType);

            if (! $tariff) {
                throw new RuntimeException("{$period->label()} için tarife tanımlanmamış.");
            }

            $tariff->loadMissing('prices');

            // --- Kişi başı günlük ücretler ---
            $lines = [];
            $dailySum = 0.0;

            foreach ($input->guests as $i => $guest) {
                $unit = $this->unitPrice($tariff, $guest, $categories[$i], $roomType, $surcharge);
                $dailySum += $unit;

                $lines[] = [
                    'guest_index' => $i,
                    'name' => $guest->name,
                    'age_category' => $categories[$i],
                    'unit_price' => round($unit, 2),
                ];
            }

            // --- Villa asgari günlük tutarı (Madde 8/3) ---
            $minDaily = $this->villaMinimumDaily($tariff, $roomType, $input->guests[0], $surcharge);
            $effectiveDaily = max($dailySum, $minDaily);
            $minimumApplied = $effectiveDaily > $dailySum;

            foreach ($lines as $line) {
                $guestTotals[$line['guest_index']] += $line['unit_price'] * $nights;
            }

            if ($minimumApplied) {
                $villaMinimumAdjustment += round(($effectiveDaily - $dailySum) * $nights, 2);
            }

            // --- Boş yatak ücreti (Madde 8/9-10) ---
            $beds = $input->emptyBedOverride ?? $this->emptyBeds($roomType, $bedOccupants);
            $fee = $tariff->emptyBedFee();
            $segmentEmptyBed = round($beds * $fee * $nights, 2);
            $emptyBedTotal += $segmentEmptyBed;

            // Rezervasyon üzerinde tek bir değer saklanır; devreler farklı tarifedeyse
            // en yüksek günlük ücret gösterilir.
            $emptyBedCount = max($emptyBedCount, $beds);
            $emptyBedFeePerDay = max($emptyBedFeePerDay, $fee);

            $segments[] = [
                'index' => $index,
                'period_id' => $period->id,
                'period_number' => $period->number,
                'period_label' => $period->label(),
                'date_range' => $period->dateRange(),
                'tariff_id' => $tariff->id,
                'tariff_name' => $tariff->name,
                'is_discounted' => $tariff->is_discounted,
                'nights' => $nights,
                'lines' => $lines,
                'daily_sum' => round($dailySum, 2),
                'min_daily_total' => $minDaily > 0 ? round($minDaily, 2) : null,
                'minimum_applied' => $minimumApplied,
                'subtotal' => round($effectiveDaily * $nights, 2),
                'empty_bed_count' => $beds,
                'empty_bed_fee_per_day' => $fee,
                'empty_bed_total' => $segmentEmptyBed,
            ];
        }

        $nights = $input->nights();

        $guestLines = [];
        foreach ($input->guests as $i => $guest) {
            $lineTotal = round($guestTotals[$i], 2);

            $guestLines[] = [
                'guest_index' => $i,
                'key' => $guest->key,
                'name' => $guest->name,
                'customer_group_id' => $guest->customerGroupId,
                'age_category' => $categories[$i],
                'wants_meal' => $guest->wantsMeal,
                'unit_price' => $nights > 0 ? round($lineTotal / $nights, 2) : 0.0,
                'line_total' => $lineTotal,
            ];
        }

        $accommodationTotal = round(array_sum(array_column($guestLines, 'line_total')) + $villaMinimumAdjustment, 2);
        $total = round($accommodationTotal + $emptyBedTotal + $input->adjustmentAmount, 2);

        return new PriceBreakdown(
            segments: $segments,
            guestLines: $guestLines,
            nights: $nights,
            surchargePerPersonDay: $surcharge,
            emptyBedCount: $emptyBedCount,
            emptyBedFeePerDay: $emptyBedFeePerDay,
            emptyBedTotal: round($emptyBedTotal, 2),
            villaMinimumAdjustment: round($villaMinimumAdjustment, 2),
            accommodationTotal: $accommodationTotal,
            adjustmentAmount: round($input->adjustmentAmount, 2),
            total: $total,
            depositAmount: $this->deposit(count($input->guests), count($periods)),
            billedPersons: $billedPersons,
            bedOccupants: $bedOccupants,
        );
    }

    /**
     * Gecelerin devrelere dağılımı.
     *
     * Tek devre 6 gecedir. İki ardışık devre birleştirildiğinde araya giren Cumartesi
     * gecesiyle birlikte toplam 13 gece olur ("iki devre (13 gün)"); bu köprü gecesi,
     * konuğun devam ettiği ikinci devrenin tarifesinden ücretlendirilir.
     *
     * @param  list<Period>  $periods
     * @return list<array{0: Period, 1: int}>
     */
    private function nightAllocation(array $periods): array
    {
        if (count($periods) === 1) {
            $p = $periods[0];

            return [[$p, (int) $p->start_date->diffInDays($p->end_date)]];
        }

        [$first, $second] = $periods;

        return [
            [$first, (int) $first->start_date->diffInDays($first->end_date)],
            [$second, (int) $first->end_date->diffInDays($second->end_date)],
        ];
    }

    /**
     * Bir kişinin günlük birim ücreti.
     */
    private function unitPrice(
        Tariff $tariff,
        GuestInput $guest,
        string $category,
        \App\Models\RoomType $roomType,
        float $surcharge,
    ): float {
        $price = $tariff->priceFor($guest->customerGroupId);

        if (! $price) {
            throw new RuntimeException("{$tariff->name} tarifesinde bu müşteri grubu için ücret tanımlanmamış.");
        }

        $base = (float) $price->adult_price;

        // Zemin kat odalarında Tablo 1 ücretlerinden %10 indirim uygulanır.
        if ($roomType->is_ground_floor) {
            $base *= 1 - Setting::number('ground_floor.discount_rate', 0.10);
        }

        $unit = match ($category) {
            'child_0_5' => $guest->wantsMeal
                ? $base * Setting::number('child.free_meal_rate', 0.40)
                : 0.0,
            'child_6_11' => $price->child_price !== null
                ? (float) $price->child_price
                : $base * Setting::number('child.discount_rate', 0.60),
            default => $base,
        };

        // Müracaat tarihine göre ilave ücret yalnızca yatak tahsis edilen kişilerden alınır.
        if ($category !== 'child_0_5') {
            $unit += $surcharge;
        }

        return round($unit, 2);
    }

    /**
     * Villalar en az beş kişi üzerinden ücretlendirilir (Madde 8/3). Tablo 2'deki
     * "En düşük Günlük Ücret", başvuru sahibinin grubuna göre belirlenir.
     */
    private function villaMinimumDaily(
        Tariff $tariff,
        \App\Models\RoomType $roomType,
        GuestInput $primaryGuest,
        float $surcharge,
    ): float {
        if (! $roomType->isVilla()) {
            return 0.0;
        }

        $price = $tariff->priceFor($primaryGuest->customerGroupId);

        if (! $price || $price->min_daily_total === null) {
            return 0.0;
        }

        $minPersons = $roomType->min_billed_persons ?? 0;

        return (float) $price->min_daily_total + ($minPersons * $surcharge);
    }

    /**
     * Odada boş kalan yatak sayısı. Villalarda asgari tutar kuralı geçerli
     * olduğundan boş yatak ücreti uygulanmaz.
     */
    private function emptyBeds(\App\Models\RoomType $roomType, int $bedOccupants): int
    {
        if ($roomType->isVilla()) {
            return 0;
        }

        // Güre'de 3 kişilik odada 2 kişi konaklarsa kalan yatak ücretsizdir (Madde 8/10).
        if ($roomType->waive_empty_bed_at_occupancy !== null
            && $bedOccupants === (int) $roomType->waive_empty_bed_at_occupancy) {
            return 0;
        }

        return max(0, $roomType->bed_count - $bedOccupants);
    }

    /**
     * Müracaat tarihine göre kişi başı günlük ilave ücret.
     */
    public function surchargeFor(CarbonInterface $applicationDate): float
    {
        $date = $applicationDate->copy()->startOfDay();

        foreach (Setting::get('surcharge.tiers', []) as $tier) {
            $from = isset($tier['from']) ? \Carbon\Carbon::parse($tier['from'])->startOfDay() : null;
            $to = isset($tier['to']) && $tier['to'] ? \Carbon\Carbon::parse($tier['to'])->endOfDay() : null;

            if ($from && $date->lt($from)) {
                continue;
            }

            if ($to && $date->gt($to)) {
                continue;
            }

            return (float) ($tier['amount'] ?? 0);
        }

        return 0.0;
    }

    /**
     * Peşinat, oda/villa başına alınır. Tek kişilik konaklamalarda tutar yarıya iner.
     */
    public function deposit(int $guestCount, int $periodCount): float
    {
        $single = $guestCount === 1;

        return Setting::number(match (true) {
            $periodCount >= 2 && $single => 'deposit.two_periods_single',
            $periodCount >= 2 => 'deposit.two_periods',
            $single => 'deposit.one_period_single',
            default => 'deposit.one_period',
        });
    }

    /**
     * Bakiye son ödeme tarihi: tahsis bildiriminden itibaren 15 gün; devre başlangıcına
     * bundan az süre kaldıysa devre başlangıcı (Madde 8/8).
     */
    public function balanceDueDate(CarbonInterface $decidedAt, CarbonInterface $periodStart): CarbonInterface
    {
        $due = $decidedAt->copy()->startOfDay()->addDays((int) Setting::number('balance.due_days', 15));

        return $due->greaterThan($periodStart) ? $periodStart->copy()->startOfDay() : $due;
    }
}
