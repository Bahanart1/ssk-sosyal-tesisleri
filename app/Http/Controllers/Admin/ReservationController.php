<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RefundService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReservationController extends Controller
{
    /**
     * Başvurunun iş akışındaki durağı. Her başvuru tam olarak bir aşamaya
     * düşer; sekmeler bu sırayla dizilir ve her biri yöneticinin sıradaki
     * işini gösterir.
     */
    private const STAGES = [
        'deposit' => [
            'label' => 'Peşinat onayı bekliyor',
            'hint' => 'Üye peşinatını gönderdi. Dekontu inceleyip peşinatı onaylayın.',
        ],
        'review' => [
            'label' => 'Yer tahsisi bekliyor',
            'hint' => 'Peşinatı onaylandı. Başvuruyu inceleyip yer tahsis edin; gerekirse oda tipini ve tutarı düzeltin.',
        ],
        'balance' => [
            'label' => 'Bakiye ödemesi bekliyor',
            'hint' => 'Yer tahsis edildi, üyenin kalan tutarı ödemesi bekleniyor. Yönetici işlemi gerekmiyor.',
        ],
        'room' => [
            'label' => 'Oda ataması bekliyor',
            'hint' => 'Ödemesi tamamlandı. Oda sütunundan blok ve oda numarası seçin.',
        ],
        'done' => [
            'label' => 'Tamamlandı',
            'hint' => 'Ödemesi alındı ve odası atandı. Bu başvurularda yapılacak bir işlem kalmadı.',
        ],
        'closed' => [
            'label' => 'Reddedildi / İptal',
            'hint' => 'Karara bağlanmış, kapanmış başvurular.',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly RefundService $refunds,
    ) {}

    public function index(Request $request)
    {
        $query = Reservation::with(['user', 'facility', 'roomType', 'room', 'period', 'secondPeriod'])
            ->withCount('guests');

        // Aşama sekmeleri; status/deposit/room parametreleri de ayrıca çalışır.
        $stage = array_key_exists((string) $request->get('stage'), self::STAGES)
            ? $request->get('stage')
            : null;

        if ($stage) {
            $this->applyStage($query, $stage);
        }

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

        // Oda atanmamış başvuruları bulmak için; "Ödendi" sekmesiyle birlikte kullanılır.
        if ($room = $request->get('room')) {
            $query->where(fn ($q) => $room === 'unassigned' ? $q->whereNull('room_id') : $q->whereNotNull('room_id'));
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

        // Devre süzgeci tesise göre gruplanır; birden fazla yıl varsa etikete yıl da eklenir.
        $periods = Period::with('facility')->ordered()->get();
        $multipleYears = $periods->pluck('year')->unique()->count() > 1;

        $reservations = $query->latest()->paginate(15)->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'roomOptions' => $this->roomOptionsFor($reservations->getCollection()),
            'facilities' => Facility::ordered()->get(),
            'periodsByFacility' => $periods->groupBy(fn (Period $p) => $p->facility->name),
            'periodLabel' => fn (Period $p) => $p->label()
                . ($multipleYears ? ' (' . $p->year . ')' : '')
                . ' · ' . $p->start_date->translatedFormat('d M') . ' – ' . $p->end_date->translatedFormat('d M'),
            'stages' => self::STAGES,
            'stage' => $stage,
            'stageCounts' => $this->stageCounts(),
        ]);
    }

    /**
     * Bu oda tipinin fiziksel olarak bulunduğu bloklar. Doluluktan bağımsızdır;
     * "neden yalnızca şu blok çıkıyor" sorusunu ekranda yanıtlamak için.
     *
     * @return list<string>
     */
    private function blocksOfType(Reservation $reservation): array
    {
        return Room::where('facility_id', $reservation->facility_id)
            ->where('room_type_id', $reservation->room_type_id)
            ->where('is_active', true)
            ->distinct()
            ->orderBy('block')
            ->pluck('block')
            ->all();
    }

    /**
     * Aynı yatak sayısına sahip diğer oda tipleri (tipik olarak zemin kat
     * karşılığı). Ücreti değiştirdikleri için atama listesine karışmazlar;
     * yönetici bunları ancak oda tipini değiştirerek kullanabilir.
     *
     * @return \Illuminate\Support\Collection<int, RoomType>
     */
    private function alternateTypes(Reservation $reservation)
    {
        return RoomType::where('facility_id', $reservation->facility_id)
            ->where('kind', 'room')
            ->where('bed_count', $reservation->roomType->bed_count)
            ->whereKeyNot($reservation->room_type_id)
            ->active()
            ->has('rooms')
            ->ordered()
            ->get();
    }

    /** Aşama koşullarını sorguya uygular. */
    private function applyStage($query, string $stage): void
    {
        match ($stage) {
            // Dekont reddedilmişse üye yenisini göndereceği için yine bu aşamadadır.
            'deposit' => $query->where('status', 'pending')->where('deposit_status', '!=', 'verified'),
            'review' => $query->where('status', 'pending')->where('deposit_status', 'verified'),
            'balance' => $query->where('status', 'approved'),
            'room' => $query->where('status', 'paid')->whereNull('room_id'),
            'done' => $query->where('status', 'paid')->whereNotNull('room_id'),
            'closed' => $query->whereIn('status', ['rejected', 'cancelled']),
        };
    }

    /**
     * Sekme rozetleri. Tek bir toplu sorgudan hesaplanır; aşama sayısı kadar
     * ayrı sayım sorgusu atmaya gerek yok.
     *
     * @return array<string, int>
     */
    private function stageCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::STAGES), 0);

        $rows = Reservation::query()
            ->selectRaw('status, deposit_status, room_id is null as odasiz, count(*) as total')
            ->groupBy('status', 'deposit_status', 'odasiz')
            ->get();

        foreach ($rows as $row) {
            $stage = match (true) {
                in_array($row->status, ['rejected', 'cancelled'], true) => 'closed',
                $row->status === 'pending' => $row->deposit_status === 'verified' ? 'review' : 'deposit',
                $row->status === 'approved' => 'balance',
                $row->status === 'paid' => $row->odasiz ? 'room' : 'done',
                default => null,
            };

            if ($stage) {
                $counts[$stage] += (int) $row->total;
            }
        }

        return $counts;
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'user.customerGroup', 'facility', 'roomType', 'room', 'period', 'secondPeriod',
            'guests.customerGroup', 'payments.verifier', 'approver',
        ]);

        return view('admin.reservations.show', [
            'reservation' => $reservation,
            'availableRooms' => $this->availableRooms($reservation),
            'roomTypeBlocks' => $this->blocksOfType($reservation),
            'alternateTypes' => $this->alternateTypes($reservation),
        ]);
    }

    /**
     * Yönetici, talebi ödemeye göndermeden önce oda tipini, devreleri, kişi
     * listesini ve tutarı değiştirebilir (Madde 5/9, 6/6).
     */
    public function edit(Reservation $reservation)
    {
        $reservation->load(['user', 'facility', 'roomType', 'room', 'period', 'secondPeriod', 'guests.customerGroup']);

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

    /**
     * Fiziksel oda ataması. Düzenleme formundan ayrıdır; ödemesi tamamlanmış
     * başvuruya da oda verilebilmeli, oda değişikliği ücreti etkilemez.
     */
    public function assignRoom(Request $request, Reservation $reservation)
    {
        abort_unless(in_array($reservation->status, Room::OCCUPYING_STATUSES, true), 422);

        $data = $request->validate([
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ], [], ['room_id' => 'oda']);

        if (empty($data['room_id'])) {
            $reservation->update(['room_id' => null]);

            return back()->with('success', 'Oda ataması kaldırıldı.');
        }

        $periodIds = array_filter([$reservation->period_id, $reservation->second_period_id]);
        $room = Room::find($data['room_id']);

        $uygun = $room
            && $room->facility_id === $reservation->facility_id
            && $room->room_type_id === $reservation->room_type_id
            && $room->is_active
            && Room::whereKey($room->id)->freeForPeriods($periodIds, $reservation->id)->exists();

        if (! $uygun) {
            return back()->withErrors([
                'room_id' => 'Seçilen oda bu devrede uygun değil. Oda bu arada başkasına verilmiş olabilir; sayfayı yenileyin.',
            ]);
        }

        $reservation->update(['room_id' => $room->id]);

        return back()->with('success', "{$room->label()} odası tahsis edildi.");
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

        $refund = $this->refunds->open($reservation, 'cancelled');

        return back()->with('success', $refund
            ? 'Başvuru iptal edildi. İade kaydı açıldı; üyeden IBAN bilgisi istenecek.'
            : 'Başvuru iptal edildi.');
    }

    /**
     * Listedeki her başvuru için atanabilir odalar. Satır başına sorgu atmamak
     * adına tüm odalar ve dolu kayıtlar iki sorguda çekilip bellekte eşlenir.
     *
     * @param  \Illuminate\Support\Collection<int, Reservation>  $reservations
     * @return array<int, \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>>
     */
    private function roomOptionsFor($reservations): array
    {
        $atanabilir = $reservations->filter(fn (Reservation $r) => in_array($r->status, Room::OCCUPYING_STATUSES, true));

        if ($atanabilir->isEmpty()) {
            return [];
        }

        $rooms = Room::whereIn('facility_id', $atanabilir->pluck('facility_id')->unique())
            ->whereIn('room_type_id', $atanabilir->pluck('room_type_id')->unique())
            ->where('is_active', true)
            ->ordered()
            ->get();

        $devreler = $atanabilir
            ->flatMap(fn (Reservation $r) => array_filter([$r->period_id, $r->second_period_id]))
            ->unique();

        // Odayı hangi devrede kimin işgal ettiği: [devre => [oda kimlikleri]]
        $dolu = [];

        Reservation::whereNotNull('room_id')
            ->whereIn('status', Room::OCCUPYING_STATUSES)
            ->where(fn ($q) => $q->whereIn('period_id', $devreler)->orWhereIn('second_period_id', $devreler))
            ->get(['id', 'room_id', 'period_id', 'second_period_id'])
            ->each(function (Reservation $r) use (&$dolu) {
                foreach (array_filter([$r->period_id, $r->second_period_id]) as $devre) {
                    $dolu[$devre][$r->room_id] = $r->id;
                }
            });

        $secenekler = [];

        foreach ($atanabilir as $reservation) {
            $secenekler[$reservation->id] = $rooms
                ->where('facility_id', $reservation->facility_id)
                ->where('room_type_id', $reservation->room_type_id)
                ->reject(function (Room $room) use ($reservation, $dolu) {
                    foreach (array_filter([$reservation->period_id, $reservation->second_period_id]) as $devre) {
                        $sahibi = $dolu[$devre][$room->id] ?? null;

                        if ($sahibi && $sahibi !== $reservation->id) {
                            return true;
                        }
                    }

                    return false;
                })
                ->groupBy('block');
        }

        return $secenekler;
    }

    /**
     * Bu başvuruya atanabilecek odalar: doğru tesis ve oda tipinde, aktif ve
     * seçilen devre(ler)de başka bir başvuruya verilmemiş olanlar. Başvurunun
     * hâlihazırda atanmış odası listede kalır.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection>
     */
    private function availableRooms(Reservation $reservation)
    {
        $periodIds = array_filter([$reservation->period_id, $reservation->second_period_id]);

        return Room::where('facility_id', $reservation->facility_id)
            ->where('room_type_id', $reservation->room_type_id)
            ->active()
            ->freeForPeriods($periodIds, $reservation->id)
            ->ordered()
            ->get()
            ->groupBy('block');
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
