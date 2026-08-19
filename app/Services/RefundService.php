<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Rules\Iban;
use Carbon\CarbonInterface;

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

        $deduction = min($paid, $this->deductionFor($reservation, $reason));

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

    /**
     * İadeden düşülecek tutar. Üç ayrı durum vardır:
     *
     *  - Yer tahsis edilemeyen başvuru (rejected): kesinti yok, faizsiz tam iade.
     *  - Normal iptal: peşinattan kırtasiye ve hizmet bedeli kesilir.
     *  - Devre başlangıcına 10 günden az kala sebepsiz iptal: iki günlük konaklama
     *    bedeli (konaklama tutarının yaklaşık üçte biri) alınır.
     *  - Konaklarken erken ayrılış: kalınan günlerin tamamı + kalınmayan günlerin
     *    yarısı tahsil edilir; kalanı iade edilir.
     */
    public function deductionFor(Reservation $reservation, string $reason, ?CarbonInterface $departureDate = null): float
    {
        if ($reason === 'rejected') {
            return 0.0;
        }

        $accommodation = (float) $reservation->accommodation_total ?: (float) $reservation->total_price;

        if ($reason === 'early_departure') {
            $nights = max(1, (int) $reservation->nights);
            $departure = ($departureDate ?? now())->copy()->startOfDay();

            $stayed = (int) $reservation->start_date->copy()->startOfDay()->diffInDays($departure, false);
            $stayed = max(0, min($nights, $stayed));
            $unused = $nights - $stayed;

            $ratio = (float) Setting::number('refund.early_departure_ratio', 0.5);
            $nightly = $accommodation / $nights;

            return round($nightly * $stayed + $nightly * $unused * $ratio, 2);
        }

        // Devre başlangıcına kalan gün, geç iptal eşiğinin altındaysa oransal kesinti.
        $minDays = (int) Setting::number('cancellation.min_days_before', 10);
        $kalan = (int) now()->startOfDay()->diffInDays($reservation->start_date, false);

        if ($kalan < $minDays) {
            return round($accommodation * (float) Setting::number('refund.late_cancel_ratio', 0.3333), 2);
        }

        return (float) Setting::number('refund.deposit_fee', 500);
    }

    /**
     * Kişi çıkarma vb. sonrası fazla ödeme iadesi. Talep gerektirmez ve IBAN
     * beklemez: kayıt doğrudan "ödeme bekleniyor" durumuna düşer, üye panelinde
     * tutarı görür, iade yapılınca yönetici ödendi olarak işaretler.
     *
     * Aynı rezervasyonun açık iadesi varsa tutar güncellenir; ödenmiş bir iade
     * varsa kayıt yeni tutar için yeniden açılır (geçmiş nota işlenir).
     */
    public function openOverpayment(Reservation $reservation, float $amount): Refund
    {
        $refund = Refund::firstOrNew(['reservation_id' => $reservation->id]);

        $not = $refund->exists && $refund->isPaid()
            ? trim(($refund->note ? $refund->note . "\n" : '')
                . 'Önceki iade ödendi: ' . number_format((float) $refund->amount, 2, ',', '.') . ' ₺ (' . $refund->paid_at?->format('d.m.Y') . ')')
            : $refund->note;

        $refund->fill([
            'user_id' => $reservation->user_id,
            'reason' => 'overpayment',
            'gross_amount' => $amount,
            'deduction' => 0,
            'amount' => $amount,
            'status' => 'pending',
            'note' => $not,
            'reference_no' => null,
            'paid_at' => null,
            'processed_by' => null,
        ])->save();

        return $refund;
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
