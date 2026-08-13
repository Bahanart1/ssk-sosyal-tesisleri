<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * Sanal POS yönlendirmesinden dönen adımlar.
 *
 * Banka, 3D Secure sonucunu kullanıcının tarayıcısı üzerinden POST ile geri gönderir.
 * Bu istek çapraz site olduğundan oturum çerezi taşınmayabilir; bu nedenle callback
 * oturum gerektirmez ve doğrulama, bankanın imzası (hash) üzerinden yapılır.
 */
class PaymentFlowController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Test sürücüsünün 3D Secure benzetim ekranı. */
    public function simulate(Payment $payment)
    {
        abort_unless(config('payment.driver') === 'fake', 404);
        abort_unless($payment->status === 'pending', 404);

        $payment->load('reservation');

        return view('payment.simulate', compact('payment'));
    }

    public function callback(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return $this->redirectToReservation($payment, 'Bu ödeme daha önce sonuçlandırılmış.');
        }

        $result = $this->payments->completeCardPayment($request, $payment);

        $reservation = $payment->reservation;

        if (! $result->successful) {
            return redirect()
                ->route('customer.reservations.show', $reservation)
                ->with('error', 'Ödeme tamamlanamadı: ' . $result->message);
        }

        return redirect()
            ->route('customer.reservations.show', $reservation)
            ->with('success', $payment->kind === 'deposit'
                ? 'Peşinat ödemeniz alındı. Müracaatınız değerlendirmeye alınmıştır.'
                : 'Ödemeniz başarıyla tamamlandı. İyi tatiller dileriz.');
    }

    private function redirectToReservation(Payment $payment, string $message)
    {
        return redirect()
            ->route('customer.reservations.show', $payment->reservation_id)
            ->with('error', $message);
    }
}
