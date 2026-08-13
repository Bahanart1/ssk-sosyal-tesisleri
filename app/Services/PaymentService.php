<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payment\GatewayRedirect;
use App\Services\Payment\GatewayResult;
use App\Services\Payment\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly DocumentStorage $documents,
    ) {}

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    /**
     * Kartla ödeme kaydı açıp kullanıcıyı 3D Secure adımına yönlendirir.
     */
    public function startCardPayment(Reservation $reservation, string $kind, float $amount, int $installment = 1): array
    {
        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'kind' => $kind,
            'method' => 'card',
            'amount' => $amount,
            'installment' => max(1, $installment),
            'status' => 'pending',
            'reference_no' => Payment::newReference(),
        ]);

        $redirect = $this->gateway->initiate($payment, route('payment.callback', $payment));

        return [$payment, $redirect];
    }

    /**
     * Havale/EFT bildirimi: dekont yüklenir, doğrulamayı admin yapar (Madde 6/4).
     */
    public function recordBankTransfer(
        Reservation $reservation,
        string $kind,
        float $amount,
        UploadedFile $receipt,
    ): Payment {
        return Payment::create([
            'reservation_id' => $reservation->id,
            'kind' => $kind,
            'method' => 'bank_transfer',
            'amount' => $amount,
            'status' => 'pending',
            'reference_no' => Payment::newReference(),
            'receipt_path' => $this->documents->store($receipt, DocumentStorage::RECEIPT, $reservation->id),
        ]);
    }

    /**
     * Bankadan dönen 3D Secure sonucunu işler.
     */
    public function completeCardPayment(Request $request, Payment $payment): GatewayResult
    {
        $result = $this->gateway->handleCallback($request, $payment);

        DB::transaction(function () use ($payment, $result) {
            if ($result->successful) {
                $payment->update([
                    'status' => 'success',
                    'gateway_ref' => $result->reference,
                    'gateway_payload' => $result->payload,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ]);

                $this->settle($payment);
            } else {
                $payment->update([
                    'status' => 'failed',
                    'gateway_payload' => $result->payload,
                    'failure_reason' => $result->message,
                ]);
            }
        });

        return $result;
    }

    /**
     * Admin havale dekontunu doğrular.
     */
    public function verifyTransfer(Payment $payment, User $admin): void
    {
        DB::transaction(function () use ($payment, $admin) {
            $payment->update([
                'status' => 'success',
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'paid_at' => $payment->paid_at ?? now(),
                'failure_reason' => null,
            ]);

            $this->settle($payment);
        });
    }

    public function rejectTransfer(Payment $payment, User $admin, string $reason): void
    {
        DB::transaction(function () use ($payment, $admin, $reason) {
            $payment->update([
                'status' => 'failed',
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'failure_reason' => $reason,
            ]);

            $reservation = $payment->reservation;

            if ($payment->kind === 'deposit') {
                $reservation->update(['deposit_status' => 'rejected']);
            }
        });
    }

    /**
     * Başarılı bir ödemenin rezervasyon üzerindeki etkisini uygular.
     */
    private function settle(Payment $payment): void
    {
        $reservation = $payment->reservation()->first();

        if ($payment->kind === 'deposit') {
            $reservation->update(['deposit_status' => 'verified']);
        }

        // Toplam tutar tamamen tahsil edildiyse rezervasyon ödenmiş sayılır.
        if ($reservation->status === 'approved' && $reservation->balanceDue() <= 0) {
            $reservation->update(['status' => 'paid']);
        }
    }
}
