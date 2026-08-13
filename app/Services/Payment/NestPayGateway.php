<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * NestPay / EST altyapısını kullanan banka sanal POS'ları için 3D Pay Hosting sürücüsü.
 * (Akbank, T. İş Bankası, T.C. Ziraat Bankası ve Halkbank bu altyapıyı kullanır.)
 *
 * Kullanıma almak için .env dosyasına banka tarafından verilen bilgiler girilir:
 *
 *   PAYMENT_DRIVER=nestpay
 *   NESTPAY_URL=https://<banka-3d-gateway-adresi>/fim/est3Dgate
 *   NESTPAY_CLIENT_ID=<terminal/işyeri numarası>
 *   NESTPAY_STORE_KEY=<3D store key>
 *   NESTPAY_STORE_TYPE=3d_pay_hosting
 *
 * Not: Dernek hangi bankayla anlaşırsa onun gateway adresi ve terminal bilgileri
 * kullanılmalıdır; farklı bir altyapı (Garanti, Vakıfbank, Yapı Kredi) seçilirse
 * yalnızca bu sınıfın bir eşdeğeri yazılır, akışın geri kalanı değişmez.
 */
class NestPayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'nestpay';
    }

    public function initiate(Payment $payment, string $callbackUrl): GatewayRedirect
    {
        $config = $this->config();
        $orderId = $payment->reference_no;

        $fields = [
            'clientid' => $config['client_id'],
            'storetype' => $config['store_type'],
            'oid' => $orderId,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => '949',                                  // TRY
            'taksit' => $payment->installment > 1 ? (string) $payment->installment : '',
            'okUrl' => $callbackUrl,
            'failUrl' => $callbackUrl,
            'callbackUrl' => $callbackUrl,
            'lang' => 'tr',
            'rnd' => bin2hex(random_bytes(16)),
            'hashAlgorithm' => 'ver3',
            'refreshtime' => '3',
        ];

        $fields['hash'] = $this->hash($fields, $config['store_key']);

        $payment->update([
            'gateway' => $this->name(),
            'gateway_ref' => $orderId,
        ]);

        return new GatewayRedirect(url: $config['url'], method: 'POST', fields: $fields);
    }

    public function handleCallback(Request $request, Payment $payment): GatewayResult
    {
        $payload = $request->except(['_token']);

        if (! $this->verifyHash($payload)) {
            return GatewayResult::failure('Banka yanıtının imzası doğrulanamadı.', $payload);
        }

        // 1 ve 2: tam doğrulama · 3 ve 4: yarı doğrulama (banka tercihine göre kabul edilir)
        $mdStatus = (string) ($payload['mdStatus'] ?? '');
        $response = strtolower((string) ($payload['Response'] ?? ''));

        if (! in_array($mdStatus, ['1', '2', '3', '4'], true)) {
            return GatewayResult::failure(
                $payload['mdErrorMsg'] ?? '3D Secure doğrulaması başarısız.',
                $payload
            );
        }

        if ($response !== 'approved') {
            return GatewayResult::failure(
                $payload['ErrMsg'] ?? 'Ödeme bankaca onaylanmadı.',
                $payload
            );
        }

        // Bankadan dönen tutarın istenen tutarla eşleştiği doğrulanır.
        $returned = round((float) ($payload['amount'] ?? 0), 2);
        if ($returned !== round((float) $payment->amount, 2)) {
            return GatewayResult::failure('Bankadan dönen tutar ödeme tutarıyla uyuşmuyor.', $payload);
        }

        return GatewayResult::success((string) ($payload['AuthCode'] ?? $payload['TransId'] ?? $payment->reference_no), $payload);
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

    /**
     * NestPay ver3 imzası: parametreler anahtara göre (büyük/küçük harf duyarsız)
     * sıralanır, değerlerdeki "\" ve "|" karakterleri kaçırılır, "|" ile birleştirilir,
     * sonuna store key eklenir ve SHA-512 özeti base64 ile kodlanır.
     *
     * @param  array<string, mixed>  $params
     */
    private function hash(array $params, string $storeKey): string
    {
        unset($params['hash'], $params['encoding'], $params['countdown']);

        uksort($params, fn ($a, $b) => strcasecmp($a, $b));

        $escaped = array_map(
            fn ($value) => str_replace('|', '\\|', str_replace('\\', '\\\\', (string) $value)),
            array_values($params)
        );

        $escaped[] = str_replace('|', '\\|', str_replace('\\', '\\\\', $storeKey));

        return base64_encode(hash('sha512', implode('|', $escaped), true));
    }

    /** @param array<string, mixed> $payload */
    private function verifyHash(array $payload): bool
    {
        $received = (string) ($payload['HASH'] ?? $payload['hash'] ?? '');

        if ($received === '') {
            return false;
        }

        $expected = $this->hash(
            array_diff_key($payload, array_flip(['HASH', 'hash', 'encoding', 'countdown'])),
            $this->config()['store_key']
        );

        return hash_equals($expected, $received);
    }

    /** @return array{url:string, client_id:string, store_key:string, store_type:string} */
    private function config(): array
    {
        $config = config('payment.nestpay');

        foreach (['url', 'client_id', 'store_key'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException(
                    "Sanal POS yapılandırması eksik: NESTPAY_" . strtoupper($key) . " tanımlanmamış. " .
                    "Banka bilgileri girilene kadar PAYMENT_DRIVER=fake kullanılabilir."
                );
            }
        }

        return $config;
    }
}
