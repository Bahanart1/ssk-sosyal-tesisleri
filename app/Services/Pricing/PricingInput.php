<?php

namespace App\Services\Pricing;

use App\Models\Period;
use App\Models\RoomType;
use Carbon\CarbonInterface;

class PricingInput
{
    /**
     * @param  list<GuestInput>  $guests
     * @param  float|null  $surchargeOverride  Adminin elle belirlediği geç müracaat farkı (null = tarihe göre hesapla)
     * @param  int|null  $emptyBedOverride  Adminin elle belirlediği boş yatak sayısı (null = otomatik)
     */
    public function __construct(
        public readonly RoomType $roomType,
        public readonly Period $period,
        public readonly ?Period $secondPeriod,
        public readonly array $guests,
        public readonly CarbonInterface $applicationDate,
        public readonly ?float $surchargeOverride = null,
        public readonly ?int $emptyBedOverride = null,
        public readonly float $adjustmentAmount = 0.0,
    ) {}

    /** @return list<Period> */
    public function periods(): array
    {
        return array_values(array_filter([$this->period, $this->secondPeriod]));
    }

    public function startDate(): CarbonInterface
    {
        return $this->period->start_date;
    }

    public function endDate(): CarbonInterface
    {
        return ($this->secondPeriod ?? $this->period)->end_date;
    }

    /** 1 devre → 6 gece · 2 ardışık devre → 13 gece. */
    public function nights(): int
    {
        return (int) $this->startDate()->diffInDays($this->endDate());
    }
}
