<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RoomType;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function index(Request $request)
    {
        $query = Reservation::with(['user', 'facility', 'roomType', 'period', 'secondPeriod'])
            ->withCount('guests');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($facility = $request->get('facility')) {
            $query->where('facility_id', $facility);
        }

        if ($period = $request->get('period')) {
            $query->where(fn ($q) => $q->where('period_id', $period)->orWhere('second_period_id', $period));
        }

        if ($deposit = $request->get('deposit')) {
            $query->where('deposit_status', $deposit);
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('tc_no', 'like', "%{$search}%")
                        ->orWhere('membership_no', 'like', "%{$search}%"))
                    ->orWhereHas('guests', fn ($g) => $g
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('tc_no', 'like', "%{$search}%"));
            });
        }

        return view('admin.reservations.index', [
            'reservations' => $query->latest()->paginate(15)->withQueryString(),
            'facilities' => Facility::ordered()->get(),
            'counts' => Reservation::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'user.customerGroup', 'facility', 'roomType', 'period', 'secondPeriod',
            'guests.customerGroup', 'payments.verifier', 'approver',
        ]);

        return view('admin.reservations.show', compact('reservation'));
    }

    /**
     * Yönetici, talebi ödemeye göndermeden önce oda tipini, devreleri, kişi
     * listesini ve tutarı değiştirebilir (Madde 5/9, 6/6).
     */
    public function edit(Reservation $reservation)
    {
        $reservation->load(['user', 'facility', 'roomType', 'period', 'secondPeriod', 'guests.customerGroup']);

        $preview = $this->safeQuote($reservation);

        return view('admin.reservations.edit', [
            'reservation' => $reservation,
            'roomTypes' => RoomType::where('facility_id', $reservation->facility_id)->active()->ordered()->get(),
            'periods' => Period::where('facility_id', $reservation->facility_id)
                ->where('year', $reservation->period->year)
                ->ordered()->get(),
            'groups' => CustomerGroup::ordered()->get(),
            'relations' => ReservationGuest::RELATIONS,
            'preview' => $preview,
        ]);
    }

    public function update(Request $request, Reservation $reservation)
    {
        abort_if(in_array($reservation->status, ['paid', 'cancelled'], true), 422);

        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id', 'different:period_id'],

            'guests' => ['required', 'array', 'min:1'],
            'guests.*.full_name' => ['required', 'string', 'max:120'],
            'guests.*.tc_no' => ['required', 'digits:11'],
            'guests.*.birth_date' => ['required', 'date', 'before:today'],
            'guests.*.relation' => ['required', 'string', 'in:' . implode(',', array_keys(ReservationGuest::RELATIONS))],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'guests.*.wants_meal' => ['nullable', 'boolean'],

            'empty_bed_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'surcharge_per_person_day' => ['required', 'numeric', 'min:0'],
            'adjustment_amount' => ['required', 'numeric'],
            'adjustment_note' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'in:save,approve'],
        ], [], [
            'room_type_id' => 'oda tipi',
            'period_id' => 'devre',
            'guests.*.full_name' => 'ad soyad',
            'guests.*.tc_no' => 'TC kimlik numarası',
            'guests.*.birth_date' => 'doğum tarihi',
            'adjustment_amount' => 'düzeltme tutarı',
        ]);

        $roomType = RoomType::findOrFail($data['room_type_id']);
        $period = Period::findOrFail($data['period_id']);
        $second = ! empty($data['second_period_id']) ? Period::findOrFail($data['second_period_id']) : null;

        if ($second && ! $period->canCombineWith($second)) {
            return back()->withInput()->withErrors([
                'second_period_id' => 'Yalnızca birleşen devreler listesindeki ardışık iki devre birlikte seçilebilir.',
            ]);
        }

        if (count($data['guests']) > $roomType->capacity()) {
            return back()->withInput()->withErrors([
                'guests' => "{$roomType->name} için en fazla {$roomType->capacity()} kişi seçilebilir.",
            ]);
        }

        try {
            DB::transaction(function () use ($reservation, $data, $roomType, $period, $second) {
                // Yönetici listeden çıkardığı kişiler formda gelmez.
                $keptIds = array_map('intval', array_keys($data['guests']));
                $reservation->guests()->whereNotIn('id', $keptIds)->delete();

                foreach (array_values($data['guests']) as $order => $guest) {
                    $id = $keptIds[$order];

                    $reservation->guests()->whereKey($id)->update([
                        'full_name' => $guest['full_name'],
                        'tc_no' => $guest['tc_no'],
                        'birth_date' => $guest['birth_date'],
                        'relation' => $guest['relation'],
                        'customer_group_id' => $guest['customer_group_id'],
                        'wants_meal' => (bool) ($guest['wants_meal'] ?? false),
                        'sort_order' => $order,
                    ]);
                }

                $reservation->update([
                    'facility_id' => $roomType->facility_id,
                    'room_type_id' => $roomType->id,
                    'period_id' => $period->id,
                    'second_period_id' => $second?->id,
                    'start_date' => $period->start_date,
                    'end_date' => ($second ?? $period)->end_date,
                    'adjustment_note' => $data['adjustment_note'] ?? null,
                    'admin_note' => $data['admin_note'] ?? null,
                ]);

                $reservation->refresh()->load('guests');

                $breakdown = $this->reservations->repriceExisting($reservation, [
                    'room_type' => $roomType,
                    'period' => $period,
                    'second_period' => $second,
                    'surcharge' => (float) $data['surcharge_per_person_day'],
                    // Boş bırakılırsa oda kapasitesine göre otomatik hesaplanır.
                    'empty_bed_count' => isset($data['empty_bed_count']) ? (int) $data['empty_bed_count'] : null,
                    'adjustment_amount' => (float) $data['adjustment_amount'],
                ]);

                $this->reservations->applyBreakdown($reservation, $breakdown);
            });
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['period_id' => $e->getMessage()]);
        }

        if ($data['action'] === 'approve') {
            $this->reservations->approve($reservation->refresh(), Auth::user(), $data['admin_note'] ?? null);

            return redirect()->route('admin.reservations.show', $reservation)
                ->with('success', 'Yer tahsisi yapıldı. Başvuru sahibine bakiye ödemesi için açıldı.');
        }

        return redirect()->route('admin.reservations.edit', $reservation)
            ->with('success', 'Değişiklikler kaydedildi ve tutar yeniden hesaplandı.');
    }

    public function approve(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->status === 'pending', 422);

        $this->reservations->approve($reservation, Auth::user(), $request->input('admin_note'));

        return back()->with('success', 'Yer tahsisi yapıldı. Başvuru sahibine bakiye ödemesi için açıldı.');
    }

    public function reject(Request $request, Reservation $reservation)
    {
        abort_unless(in_array($reservation->status, ['pending', 'approved'], true), 422);

        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ], [], ['admin_note' => 'gerekçe']);

        $this->reservations->reject($reservation, Auth::user(), $data['admin_note']);

        return back()->with('success', 'Başvuru reddedildi.');
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        abort_if($reservation->status === 'cancelled', 422);

        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ], [], ['admin_note' => 'gerekçe']);

        $reservation->update([
            'status' => 'cancelled',
            'admin_note' => $data['admin_note'],
            'decided_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Başvuru iptal edildi.');
    }

    /** Düzenleme ekranındaki önizleme; eksik tarife gibi durumlarda ekranı kırmaz. */
    private function safeQuote(Reservation $reservation): ?array
    {
        try {
            return $this->reservations->repriceExisting($reservation, [
                'empty_bed_count' => $reservation->empty_bed_count,
                'surcharge' => (float) $reservation->surcharge_per_person_day,
                'adjustment_amount' => (float) $reservation->adjustment_amount,
            ])->toArray();
        } catch (Throwable) {
            return null;
        }
    }
}
