<?php

namespace App\Services\Payment;

/**
 * Kullanıcının 3D Secure doğrulaması için yönlendirileceği adres.
 * NestPay gibi altyapılar gizli alanlarla POST beklediğinden, form alanları da taşınır.
 */
class GatewayRedirect
{
    /** @param array<string, string> $fields */
    public function __construct(
        public readonly string $url,
        public readonly string $method = 'POST',
        public readonly array $fields = [],
    ) {}

    public function isPost(): bool
    {
        return strtoupper($this->method) === 'POST';
    }
}
