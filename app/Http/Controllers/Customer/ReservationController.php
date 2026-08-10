<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CampWeek;
use App\Models\Facility;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    /**
     * Haftalık kamp rezervasyonu oluşturma ekranı.
     */
    public function create()
    {
        $user = Auth::user();

        if (! $user->customer_class_id) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Hesabınıza henüz bir müşteri sınıfı atanmamış. Lütfen yönetim ile iletişime geçin.');
        }

        $facilities = Facility::active()->orderBy('name')->get();
        $weeks = CampWeek::upcomingWeeks(12, onlyOpen: true);

        return view('customer.reservation.create', [
            'facilities' => $facilities,
            'customerClass' => $user->customerClass,
            'weeks' => $weeks,
            'campNights' => Reservation::CAMP_NIGHTS,
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

        $checkIn = Carbon::parse($data['check_in'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out'])->startOfDay();

        if (! Reservation::isValidCampWeek($checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                'check_in' => 'Rezervasyon yalnızca Pazartesi giriş – sonraki Pazartesi çıkış (1 haftalık kamp) olarak yapılabilir.',
            ]);
        }

        if ($checkIn->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'check_in' => 'Geçmiş bir kamp haftası seçilemez.',
            ]);
        }

        if (! CampWeek::isOpen($checkIn)) {
            throw ValidationException::withMessages([
                'check_in' => 'Seçtiğiniz kamp haftası yönetici tarafından kapatılmış. Lütfen başka bir hafta seçin.',
            ]);
        }

        $nights = Reservation::CAMP_NIGHTS;

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
            ->with('success', 'Kamp rezervasyon talebiniz başarıyla oluşturuldu.');
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
