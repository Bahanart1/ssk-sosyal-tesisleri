<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /** Ödemeyi başlatır ve kullanıcının yönlendirileceği adresi döner. */
    public function initiate(Payment $payment, string $callbackUrl): GatewayRedirect;

    /** Bankadan dönen isteği doğrular. */
    public function handleCallback(Request $request, Payment $payment): GatewayResult;

    /**
     * Taksit seçenekleri (Madde 8/8 — bakiye peşin veya banka kartına taksitle ödenir).
     *
     * @return array<int, array{installment:int, label:string, total:float, monthly:float}>
     */
    public function installmentOptions(float $amount): array;
}
