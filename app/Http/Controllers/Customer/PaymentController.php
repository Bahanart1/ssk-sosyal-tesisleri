<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function show(Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);
        abort_unless($reservation->status === 'approved', 404);

        return view('customer.reservation.payment', compact('reservation'));
    }

    public function process(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);
        abort_unless($reservation->status === 'approved', 404);

        $request->validate([
            'method' => ['required', 'in:credit_card,bank_transfer'],
        ]);

        Payment::create([
            'reservation_id' => $reservation->id,
            'method' => $request->method,
            'amount' => $reservation->total_price,
            'status' => 'success',
            'reference_no' => 'SSK-' . strtoupper(Str::random(10)),
            'paid_at' => now(),
        ]);

        $reservation->update(['status' => 'paid']);

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Ödemeniz başarıyla alındı. İyi tatiller dileriz!');
    }
}
