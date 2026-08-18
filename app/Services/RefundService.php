<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Rules\Iban;

/**
 * Peşinat ve ödeme iadeleri.
 *
 * Yer tahsis edilemeyen başvuruların peşinatı faizsiz ve kesintisiz iade edilir.
 * Üyenin kendi iptalinde ise Yönetim Kurulunca belirlenen kırtasiye ve hizmet
 * bedeli düşülür; bu tutar "refund.cancellation_fee" parametresinden okunur.
 */
class RefundService
{
    /**
     * Karara bağlanan başvuru için iade kaydı açar. Tahsil edilmiş para yoksa
     * kayıt oluşturulmaz. Aynı başvuruya ikinci kez çağrılırsa mevcut kaydı
     * döndürür; ödenmiş bir iade hiçbir koşulda değiştirilmez.
     */
    public function open(Reservation $reservation, string $reason): ?Refund
    {
        $paid = $reservation->paidTotal();

        if ($paid <= 0) {
            return null;
        }

        $existing = Refund::where('reservation_id', $reservation->id)->first();

        if ($existing) {
            return $existing;
        }

        $deduction = $reason === 'cancelled'
            ? min($paid, (float) Setting::number('refund.cancellation_fee', 0))
            : 0.0;

        return Refund::create([
            'reservation_id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'reason' => $reason,
            'gross_amount' => $paid,
            'deduction' => $deduction,
            'amount' => round($paid - $deduction, 2),
            'status' => 'awaiting_iban',
        ]);
    }

    /** Üye kendi hesap bilgisini bildirir; iade ödeme listesine düşer. */
    public function submitAccount(Refund $refund, string $iban, string $accountHolder): Refund
    {
        $refund->update([
            'iban' => Iban::normalize($iban),
            'account_holder' => trim($accountHolder),
            'iban_submitted_at' => now(),
            // Ödenmiş bir iadede hesap bilgisi düzeltilse bile durum geri alınmaz.
            'status' => $refund->isPaid() ? $refund->status : 'pending',
        ]);

        return $refund->fresh();
    }

    /** Havale yapıldıktan sonra yönetici kaydı kapatır. */
    public function markPaid(Refund $refund, User $admin, ?string $referenceNo = null, ?string $note = null): Refund
    {
        $refund->update([
            'status' => 'paid',
            'reference_no' => $referenceNo,
            'note' => $note,
            'paid_at' => now(),
            'processed_by' => $admin->id,
        ]);

        return $refund->fresh();
    }
}
