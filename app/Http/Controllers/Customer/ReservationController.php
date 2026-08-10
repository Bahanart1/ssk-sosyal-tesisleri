<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    /**
     * Çok adımlı rezervasyon oluşturma ekranı (tek sayfa, Alpine.js ile adım adım).
     */
    public function create()
    {
        $user = Auth::user();

        if (! $user->customer_class_id) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Hesabınıza henüz bir müşteri sınıfı atanmamış. Lütfen yönetim ile iletişime geçin.');
        }

        $facilities = Facility::active()->orderBy('name')->get();

        return view('customer.reservation.create', [
            'facilities' => $facilities,
            'customerClass' => $user->customerClass,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $customerClass = $user->customerClass;

        if (! $customerClass) {
            return back()->withErrors(['facility_id' => 'Müşteri sınıfınız tanımlı değil.']);
        }

        $checkIn = \Carbon\Carbon::parse($data['check_in']);
        $checkOut = \Carbon\Carbon::parse($data['check_out']);
        $nights = max(1, $checkIn->diffInDays($checkOut));

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'facility_id' => $data['facility_id'],
            'customer_class_id' => $customerClass->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => $data['guests'],
            'note' => $data['note'] ?? null,
            'total_price' => Reservation::calculatePrice($customerClass, $nights),
            'status' => 'pending',
        ]);

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Rezervasyon talebiniz başarıyla oluşturuldu.');
    }

    public function show(Reservation $reservation)
    {
        $this->authorizeOwner($reservation);

        $reservation->load(['facility', 'customerClass', 'payment']);

        return view('customer.reservation.show', compact('reservation'));
    }

    private function authorizeOwner(Reservation $reservation): void
    {
        abort_unless($reservation->user_id === Auth::id(), 403);
    }
}
