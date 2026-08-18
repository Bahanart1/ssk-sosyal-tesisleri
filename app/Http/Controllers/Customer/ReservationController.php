<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreReservationRequest;
use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly PaymentService $payments,
        private readonly RefundService $refunds,
    ) {}

    /** Üyenin tüm başvuruları. */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->reservations()
            ->with(['facility', 'roomType', 'period', 'secondPeriod', 'payments']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $reservations = $query->latest('created_at')->get();

        return view('customer.reservations.index', [
            'reservations' => $reservations,
            'counts' => $user->reservations()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'canApply' => $user->canApply(),
        ]);
    }

    public function create()
    {
        $user = Auth::user();

        if (! $user->customer_group_id) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Hesabınıza henüz bir müşteri grubu atanmamış. Lütfen Dernek ile iletişime geçin.');
        }

        if ($user->hasDuesDebt()) {
            return redirect()->route('customer.dashboard')
                ->with('error', 'Aidat borcunuz bulunduğu için müracaat formunuz işleme alınamaz. Borcunuzu ödedikten sonra başvurabilirsiniz.');
        }

        return view('customer.reservation.create', [
            'facilities' => $this->wizardFacilities(),
            'groups' => CustomerGroup::ordered()->get(),
            'relations' => ReservationGuest::RELATIONS,
            'bankAccounts' => Setting::get('bank_accounts', []),
            'deposits' => [
                'one_period' => Setting::number('deposit.one_period'),
                'two_periods' => Setting::number('deposit.two_periods'),
                'one_period_single' => Setting::number('deposit.one_period_single'),
                'two_periods_single' => Setting::number('deposit.two_periods_single'),
            ],
        ]);
    }

    /**
     * Sihirbazın canlı fiyat özeti. Hesap tarayıcıda tekrarlanmaz; tek doğruluk
     * kaynağı sunucudaki fiyat motorudur.
     */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'guests' => ['required', 'array', 'min:1', 'max:12'],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'guests.*.birth_date' => ['required', 'date'],
            'guests.*.wants_meal' => ['nullable', 'boolean'],
            'guests.*.full_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $breakdown = $this->reservations->quote($this->reservations->buildPricingInput($data));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($breakdown->toArray());
    }

    public function store(StoreReservationRequest $request)
    {
        $data = $request->validated();

        $documents = [];
        foreach (array_values($request->file('guests', [])) as $index => $files) {
            if (isset($files['document'])) {
                $documents[$index] = $files['document'];
            }
        }

        try {
            $reservation = $this->reservations->create(
                Auth::user(),
                $data,
                $documents,
                $request->file('health_report'),
            );
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['period_id' => $e->getMessage()]);
        }

        // Peşinat: ya sanal POS'tan tahsil edilir ya da havale dekontu ile bildirilir.
        if ($data['deposit_method'] === 'card') {
            [$payment, $redirect] = $this->payments->startCardPayment(
                $reservation,
                'deposit',
                (float) $reservation->deposit_amount,
            );

            return view('customer.payment.redirect', compact('payment', 'redirect'));
        }

        $this->payments->recordBankTransfer(
            $reservation,
            'deposit',
            (float) $reservation->deposit_amount,
            $request->file('deposit_receipt'),
        );

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Müracaatınız alındı. Peşinat dekontunuz incelendikten sonra değerlendirmeye alınacaktır.');
    }

    public function show(Reservation $reservation)
    {
        $this->authorizeOwner($reservation);

        $reservation->load([
            'facility', 'roomType', 'room', 'period', 'secondPeriod',
            'guests.customerGroup', 'payments', 'refund',
        ]);

        return view('customer.reservation.show', [
            'reservation' => $reservation,
            'bankAccounts' => Setting::get('bank_accounts', []),
        ]);
    }

    /** Devre başlangıcına en az 10 gün varsa iptal edilebilir (Madde 8/11). */
    public function cancel(Request $request, Reservation $reservation)
    {
        $this->authorizeOwner($reservation);

        if (! $reservation->isCancellable()) {
            return back()->with('error', 'Bu başvuru artık iptal edilemez. Devre başlangıcına en az '
                . (int) Setting::number('cancellation.min_days_before', 10) . ' gün kalmış olmalıdır.');
        }

        $reservation->update([
            'status' => 'cancelled',
            'admin_note' => trim(($reservation->admin_note ? $reservation->admin_note . "\n" : '')
                . 'Müşteri tarafından iptal edildi: ' . $request->input('reason', '-')),
        ]);

        $refund = $this->refunds->open($reservation, 'cancelled');

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', $refund
                ? 'Başvurunuz iptal edildi. İade için hesap bilgilerinizi bu sayfadan bildirin.'
                : 'Başvurunuz iptal edildi.');
    }

    /**
     * Sihirbaza gönderilen tesis / oda tipi / devre ağacı.
     *
     * @return array<int, array<string, mixed>>
     */
    private function wizardFacilities(): array
    {
        $facilities = Facility::active()->ordered()
            ->with([
                'roomTypes' => fn ($q) => $q->active()->ordered(),
                'periods' => fn ($q) => $q->open()->upcoming()->ordered(),
            ])
            ->get();

        return $facilities->map(function (Facility $facility) {
            $periods = $facility->periods;

            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'location' => $facility->location,
                'description' => $facility->description,
                'cover' => $facility->coverUrl(),
                'gallery' => $facility->galleryUrls(5),
                'room_types' => $facility->roomTypes->map(fn ($rt) => [
                    'id' => $rt->id,
                    'name' => $rt->name,
                    'kind' => $rt->kind,
                    'bed_count' => $rt->bed_count,
                    'capacity' => $rt->capacity(),
                    'min_billed_persons' => $rt->min_billed_persons,
                    'is_ground_floor' => $rt->is_ground_floor,
                    'description' => $rt->description,
                ])->values(),
                'periods' => $periods->map(function (Period $p) use ($periods) {
                    // Birleşen devreler: aynı grup içindeki bir sonraki devre
                    $partner = $periods->first(fn (Period $o) => $p->canCombineWith($o));

                    return [
                        'id' => $p->id,
                        'number' => $p->number,
                        'label' => $p->label(),
                        'date_range' => $p->dateRange(),
                        'start_date' => $p->start_date->toDateString(),
                        'end_date' => $p->end_date->toDateString(),
                        'nights' => $p->nights,
                        'is_discounted' => $p->is_discounted,
                        'note' => $p->note,
                        'combinable_with' => $partner?->id,
                        'combinable_label' => $partner ? $partner->label() . ' · ' . $partner->dateRange() : null,
                    ];
                })->values(),
            ];
        })->values()->all();
    }

    private function authorizeOwner(Reservation $reservation): void
    {
        abort_unless($reservation->user_id === Auth::id(), 403);
    }
}
