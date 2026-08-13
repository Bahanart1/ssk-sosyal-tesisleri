<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Banka sanal POS'u devreye alınana kadar kullanılan test sürücüsü.
 *
 * Gerçek bir 3D Secure sayfası yerine uygulama içindeki simülasyon ekranına yönlendirir;
 * böylece tüm ödeme akışı (başlatma → doğrulama → sonuç) uçtan uca çalıştırılabilir.
 * .env dosyasına banka bilgileri girilip PAYMENT_DRIVER=nestpay yapıldığında
 * NestPayGateway devreye girer ve akışın geri kalanı değişmez.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function initiate(Payment $payment, string $callbackUrl): GatewayRedirect
    {
        $payment->update([
            'gateway' => $this->name(),
            'gateway_ref' => 'SIM-' . strtoupper(Str::random(12)),
        ]);

        return new GatewayRedirect(
            url: route('payment.simulate', $payment),
            method: 'GET',
        );
    }

    public function handleCallback(Request $request, Payment $payment): GatewayResult
    {
        $payload = $request->except(['_token']);

        if ($request->input('decision') !== 'approve') {
            return GatewayResult::failure('Kart doğrulaması iptal edildi.', $payload);
        }

        return GatewayResult::success($payment->gateway_ref ?? 'SIM-' . strtoupper(Str::random(12)), $payload);
    }

    public function installmentOptions(float $amount): array
    {
        $options = [];

        foreach (config('payment.installments', [1]) as $count) {
            $count = (int) $count;
            $rate = (float) (config('payment.installment_rates')[$count] ?? 0);
            $total = round($amount * (1 + $rate), 2);

            $options[] = [
                'installment' => $count,
                'label' => $count === 1 ? 'Tek çekim' : "{$count} taksit",
                'total' => $total,
                'monthly' => round($total / $count, 2),
            ];
        }

        return $options;
    }
}
