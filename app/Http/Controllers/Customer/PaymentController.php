<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Setting;
use App\Services\DocumentStorage;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Reservation $reservation)
    {
        $this->authorizePayable($reservation);

        $reservation->load(['facility', 'roomType', 'period', 'secondPeriod', 'payments']);

        return view('customer.payment.show', [
            'reservation' => $reservation,
            'balance' => $reservation->balanceDue(),
            'installments' => $this->payments->gateway()->installmentOptions($reservation->balanceDue()),
            'bankAccounts' => Setting::get('bank_accounts', []),
        ]);
    }

    /** Bakiyeyi sanal POS üzerinden tahsil eder (taksit seçenekleriyle). */
    public function card(Request $request, Reservation $reservation)
    {
        $this->authorizePayable($reservation);

        $data = $request->validate([
            'installment' => ['required', 'integer', 'in:' . implode(',', config('payment.installments', [1]))],
        ]);

        $balance = $reservation->balanceDue();

        if ($balance <= 0) {
            return back()->with('error', 'Bu başvuru için ödenecek bakiye bulunmuyor.');
        }

        [$payment, $redirect] = $this->payments->startCardPayment(
            $reservation,
            'balance',
            $balance,
            (int) $data['installment'],
        );

        return view('customer.payment.redirect', compact('payment', 'redirect'));
    }

    /** Bakiyeyi havale ile ödeyip dekont bildirir. */
    public function transfer(Request $request, Reservation $reservation)
    {
        $this->authorizePayable($reservation);

        $request->validate([
            'receipt' => ['required', ...DocumentStorage::RULES],
        ], [], ['receipt' => 'banka dekontu']);

        $balance = $reservation->balanceDue();

        if ($balance <= 0) {
            return back()->with('error', 'Bu başvuru için ödenecek bakiye bulunmuyor.');
        }

        $this->payments->recordBankTransfer($reservation, 'balance', $balance, $request->file('receipt'));

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Dekontunuz alındı. Yönetim tarafından doğrulandıktan sonra ödemeniz tamamlanmış sayılacaktır.');
    }

    /** Bakiyeyi tesiste ödemeyi beyan eder; tahsilat girişte yapılır. */
    public function onSite(Reservation $reservation)
    {
        $this->authorizePayable($reservation);

        $balance = $reservation->balanceDue();

        if ($balance <= 0) {
            return back()->with('error', 'Bu rezervasyon için ödenecek bakiye bulunmuyor.');
        }

        $mevcut = $reservation->payments()
            ->where('method', 'on_site')
            ->where('status', 'pending')
            ->first();

        // Çift tıklama ya da yarım kalmış kayıt: yeni kayıt açmadan tutarı
        // güncelleyip rezervasyonu kesinleştirmek yeterli.
        if ($mevcut) {
            $mevcut->update(['amount' => $balance]);
        } else {
            $this->payments->recordOnSite($reservation, 'balance', $balance);
        }

        // Başvuru burada sonlanır: üyeden beklenen işlem kalmaz, kayıt
        // kesinleşmiş rezervasyona döner ve oda ataması sırasına girer.
        $reservation->update(['collect_on_site_at' => now()]);

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Rezervasyonunuz kesinleşti. Bakiyeyi tesise girişte ödeyeceksiniz.');
    }

    private function authorizePayable(Reservation $reservation): void
    {
        abort_unless($reservation->user_id === Auth::id(), 403);
        abort_unless($reservation->status === 'approved', 404);
    }
}
