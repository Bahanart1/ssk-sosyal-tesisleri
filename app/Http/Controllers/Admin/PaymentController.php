<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        $query = Payment::with(['reservation.user', 'verifier']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($kind = $request->get('kind')) {
            $query->where('kind', $kind);
        }

        if ($method = $request->get('method')) {
            $query->where('method', $method);
        }

        return view('admin.payments.index', [
            'payments' => $query->latest()->paginate(20)->withQueryString(),
            'pendingCount' => Payment::where('status', 'pending')->where('method', 'bank_transfer')->count(),
        ]);
    }

    /** Havale/EFT dekontunun doğrulanması (Madde 6/4). */
    public function verify(Payment $payment)
    {
        abort_unless($payment->method === 'bank_transfer' && $payment->status === 'pending', 422);

        $this->payments->verifyTransfer($payment, Auth::user());

        return back()->with('success', 'Ödeme doğrulandı.');
    }

    public function reject(Request $request, Payment $payment)
    {
        abort_unless($payment->method === 'bank_transfer' && $payment->status === 'pending', 422);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => 'gerekçe']);

        $this->payments->rejectTransfer($payment, Auth::user(), $data['reason']);

        return back()->with('success', 'Dekont reddedildi ve başvuru sahibine bildirildi.');
    }
}
