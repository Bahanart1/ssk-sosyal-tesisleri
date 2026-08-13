<?php

namespace App\Services\Payment;

class GatewayResult
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    /** @param array<string, mixed> $payload */
    public static function success(string $reference, array $payload = []): self
    {
        return new self(true, $reference, null, $payload);
    }

    /** @param array<string, mixed> $payload */
    public static function failure(string $message, array $payload = []): self
    {
        return new self(false, null, $message, $payload);
    }
}
