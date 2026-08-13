<?php

namespace App\Services\Pricing;

/**
 * Fiyat dökümü. Onay anında rezervasyona JSON olarak yazılır (denetim izi);
 * tarifeler sonradan değişse de onaylı başvurunun tutarı sabit kalır.
 */
class PriceBreakdown
{
    /**
     * @param  list<array<string, mixed>>  $segments   Devre bazlı döküm
     * @param  list<array<string, mixed>>  $guestLines Kişi bazlı toplamlar
     */
    public function __construct(
        public readonly array $segments,
        public readonly array $guestLines,
        public readonly int $nights,
        public readonly float $surchargePerPersonDay,
        public readonly int $emptyBedCount,
        public readonly float $emptyBedFeePerDay,
        public readonly float $emptyBedTotal,
        public readonly float $villaMinimumAdjustment,
        public readonly float $accommodationTotal,
        public readonly float $adjustmentAmount,
        public readonly float $total,
        public readonly float $depositAmount,
        public readonly int $billedPersons,
        public readonly int $bedOccupants,
    ) {}

    public function balanceAfterDeposit(): float
    {
        return round(max(0, $this->total - $this->depositAmount), 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'segments' => $this->segments,
            'guest_lines' => $this->guestLines,
            'nights' => $this->nights,
            'surcharge_per_person_day' => $this->surchargePerPersonDay,
            'empty_bed_count' => $this->emptyBedCount,
            'empty_bed_fee_per_day' => $this->emptyBedFeePerDay,
            'empty_bed_total' => $this->emptyBedTotal,
            'villa_minimum_adjustment' => $this->villaMinimumAdjustment,
            'accommodation_total' => $this->accommodationTotal,
            'adjustment_amount' => $this->adjustmentAmount,
            'total' => $this->total,
            'deposit_amount' => $this->depositAmount,
            'billed_persons' => $this->billedPersons,
            'bed_occupants' => $this->bedOccupants,
        ];
    }
}
