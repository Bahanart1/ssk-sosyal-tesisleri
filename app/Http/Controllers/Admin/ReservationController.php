<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::with(['user', 'facility', 'customerClass']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('q')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tc_no', 'like', "%{$search}%");
            });
        }

        $reservations = $query->latest()->paginate(12)->withQueryString();

        return view('admin.reservations.index', compact('reservations'));
    }

    public function show(Reservation $reservation)
    {
        $reservation->load(['user', 'facility', 'customerClass', 'payment']);

        return view('admin.reservations.show', compact('reservation'));
    }

    public function approve(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->status === 'pending', 422);

        $reservation->update([
            'status' => 'approved',
            'admin_note' => $request->input('admin_note'),
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Rezervasyon onaylandı. Müşteri ödeme yapabilir.');
    }

    public function reject(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->status === 'pending', 422);

        $request->validate(['admin_note' => ['required', 'string', 'max:1000']]);

        $reservation->update([
            'status' => 'rejected',
            'admin_note' => $request->input('admin_note'),
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Rezervasyon reddedildi.');
    }
}
