<?php

namespace App\Services\Pricing;

use Carbon\CarbonInterface;

/**
 * Fiyatlandırmaya giren tek bir kişi. Her kişi kendi müşteri grubuna ve
 * devre başlangıcındaki yaşına göre ücretlendirilir.
 */
class GuestInput
{
    public function __construct(
        public readonly int $customerGroupId,
        public readonly CarbonInterface $birthDate,
        public readonly bool $wantsMeal = false,
        public readonly ?string $name = null,
        public readonly mixed $key = null,
    ) {}
}
