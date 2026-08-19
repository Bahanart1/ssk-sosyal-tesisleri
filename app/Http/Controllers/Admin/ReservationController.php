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
use App\Models\User;
use App\Services\DocumentStorage;
use App\Services\RefundService;
use App\Services\ReservationService;
use App\Support\SearchText;
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
            'hint' => 'Ödemesi tamamlanmış ya da bakiyesi tesiste alınacak kesinleşmiş rezervasyonlar. Oda sütunundan blok ve oda numarası seçin.',
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
        private readonly DocumentStorage $documents,
    ) {}

    public function index(Request $request)
    {
        $query = Reservation::with(['user', 'facility', 'roomType', 'room', 'topCustomerGroup', 'period', 'secondPeriod'])
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

        // Başvurunun en yüksek grubu; kişiler arasında I. Grup varsa başvuru I. Gruptur.
        if ($group = $request->get('group')) {
            $query->where('top_customer_group_id', $group);
        }

        // Oda atanmamış başvuruları bulmak için; "Ödendi" sekmesiyle birlikte kullanılır.
        if ($room = $request->get('room')) {
            $query->where(fn ($q) => $room === 'unassigned' ? $q->whereNull('room_id') : $q->whereNotNull('room_id'));
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $kelimeler = SearchText::tokens($search);

                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($kelimeler) {
                        foreach ($kelimeler as $kelime) {
                            $u->where('search_index', 'like', "%{$kelime}%");
                        }
                    })
                    ->orWhereHas('guests', function ($g) use ($kelimeler) {
                        foreach ($kelimeler as $kelime) {
                            $g->where('search_index', 'like', "%{$kelime}%");
                        }
                    });
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
            'groups' => CustomerGroup::ordered()->get(),
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
            // Tesiste ödeme seçildiğinde başvuru kesinleşir; bakiye kuyruğundan
            // çıkıp oda ataması sırasına geçer.
            // Tesiste ödeme seçen üye başvurusunu sonlandırmıştır: bakiye
            // kuyruğundan çıkar, kesinleşmiş rezervasyon olarak oda sırasına girer.
            'balance' => $query->where('status', 'approved')->whereNull('collect_on_site_at'),
            'room' => $query->where(fn ($q) => $this->kesinlesmis($q))->whereNull('room_id'),
            'done' => $query->where(fn ($q) => $this->kesinlesmis($q))->whereNotNull('room_id'),
            'closed' => $query->whereIn('status', ['rejected', 'cancelled']),
        };
    }

    /** Ödemesi tamamlanmış ya da bakiyesi tesiste alınacak, kesinleşmiş kayıtlar. */
    private function kesinlesmis($query): void
    {
        $query->where('status', 'paid')
            ->orWhere(fn ($o) => $o->where('status', 'approved')->whereNotNull('collect_on_site_at'));
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
            ->selectRaw('status, deposit_status, room_id is null as odasiz, collect_on_site_at is null as pesin, count(*) as total')
            ->groupBy('status', 'deposit_status', 'odasiz', 'pesin')
            ->get();

        foreach ($rows as $row) {
            $stage = match (true) {
                in_array($row->status, ['rejected', 'cancelled'], true) => 'closed',
                $row->status === 'pending' => $row->deposit_status === 'verified' ? 'review' : 'deposit',
                $row->status === 'approved' => $row->pesin
                    ? 'balance'
                    : ($row->odasiz ? 'room' : 'done'),
                $row->status === 'paid' => $row->odasiz ? 'room' : 'done',
                default => null,
            };

            if ($stage) {
                $counts[$stage] += (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Yönetici, üye adına rezervasyon oluşturur. Telefonla gelen talepler için:
     * belge zorunluluğu ve aidat kontrolü uygulanmaz, kayıt "inceleniyor"
     * durumunda açılır ve normal düzenleme akışına girer.
     */
    public function create(Request $request)
    {
        $user = $request->get('uye') ? User::customers()->findOrFail($request->get('uye')) : null;

        return view('admin.reservations.create', [
            'member' => $user,
            'members' => $user ? collect([$user]) : collect(),
            'facilities' => Facility::active()->ordered()->with([
                'roomTypes' => fn ($q) => $q->active()->ordered(),
                'periods' => fn ($q) => $q->ordered(),
            ])->get(),
            'groups' => CustomerGroup::ordered()->get(),
            'relations' => ReservationGuest::RELATIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id', 'different:period_id'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.full_name' => ['required', 'string', 'max:120'],
            'guests.*.tc_no' => ['required', 'digits:11'],
            'guests.*.birth_date' => ['required', 'date', 'before:today'],
            'guests.*.relation' => ['required', 'string', 'in:' . implode(',', array_keys(ReservationGuest::RELATIONS))],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'user_id' => 'üye',
            'room_type_id' => 'oda tipi',
            'period_id' => 'devre',
            'guests.*.full_name' => 'ad soyad',
            'guests.*.tc_no' => 'TC kimlik numarası',
            'guests.*.birth_date' => 'doğum tarihi',
        ]);

        $member = User::customers()->findOrFail($data['user_id']);
        $roomType = RoomType::findOrFail($data['room_type_id']);

        if (count($data['guests']) > $roomType->capacity()) {
            return back()->withInput()->withErrors([
                'guests' => "{$roomType->name} için en fazla {$roomType->capacity()} kişi seçilebilir.",
            ]);
        }

        try {
            $reservation = $this->reservations->create($member, $data, documents: []);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['period_id' => $e->getMessage()]);
        }

        $reservation->update([
            'note' => $data['note'] ?? null,
            'admin_note' => 'Yönetici tarafından üye adına oluşturuldu.',
        ]);

        return redirect()->route('admin.reservations.edit', $reservation)
            ->with('success', 'Rezervasyon oluşturuldu. Tutarı gözden geçirip yer tahsisi yapabilirsiniz.');
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'user.customerGroup', 'facility', 'roomType', 'room', 'secondRoom', 'period', 'secondPeriod',
            'guests.customerGroup', 'payments.verifier', 'approver', 'refund',
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
        // Ödemesi tamamlanmış rezervasyonlar da düzenlenebilir: üyeler telefonla
        // kişi ekletip çıkarttırabiliyor, tutar farkı sonradan tahsil/iade ediliyor.
        abort_if($reservation->status === 'cancelled', 422);

        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id', 'different:period_id'],

            'guests' => ['required', 'array', 'min:1'],
            'guests.*.id' => ['nullable', 'integer'],
            'guests.*.full_name' => ['required', 'string', 'max:120'],
            'guests.*.tc_no' => ['required', 'digits:11'],
            'guests.*.birth_date' => ['required', 'date', 'before:today'],
            'guests.*.relation' => ['required', 'string', 'in:' . implode(',', array_keys(ReservationGuest::RELATIONS))],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'guests.*.wants_meal' => ['nullable', 'boolean'],
            'guests.*.document' => ['nullable', ...DocumentStorage::RULES],
            'guests.*.civil_registry' => ['nullable', ...DocumentStorage::RULES],

            // Ücret sistem tarafından hesaplanır; bu alanlar yalnızca istisnai
            // durumda elle girilir ve boş gelirse mevcut değer korunur.
            'empty_bed_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'surcharge_per_person_day' => ['nullable', 'numeric', 'min:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'adjustment_note' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'in:save,approve,send_payment'],
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

        // İkinci oda tahsis edilmişse kapasite iki katına çıkar.
        $kapasite = $roomType->capacity() * ($reservation->second_room_id ? 2 : 1);

        if (count($data['guests']) > $kapasite) {
            return back()->withInput()->withErrors([
                'guests' => $reservation->second_room_id
                    ? "İki {$roomType->name} için en fazla {$kapasite} kişi seçilebilir."
                    : "{$roomType->name} için en fazla {$kapasite} kişi seçilebilir. Daha fazlası için önce ikinci oda tahsis edin.",
            ]);
        }

        try {
            DB::transaction(function () use ($reservation, $data, $roomType, $period, $second) {
                // Formdaki anahtar mevcut kişinin kimliğidir; "yeni-*" anahtarları
                // yöneticinin eklediği kişilerdir. Formda gelmeyenler silinir.
                $keptIds = [];

                $order = 0;

                foreach ($data['guests'] as $anahtar => $guest) {
                    $nitelikler = [
                        'full_name' => $guest['full_name'],
                        'tc_no' => $guest['tc_no'],
                        'birth_date' => $guest['birth_date'],
                        'relation' => $guest['relation'],
                        'customer_group_id' => $guest['customer_group_id'],
                        'wants_meal' => (bool) ($guest['wants_meal'] ?? false),
                        'sort_order' => $order++,
                    ];

                    // Yönetici yüklediyse kimlik ve nüfus kaydı belgeleri de kaydedilir.
                    foreach (['document' => 'id_document_path', 'civil_registry' => 'civil_registry_path'] as $alan => $sutun) {
                        $dosya = request()->file("guests.{$anahtar}.{$alan}");

                        if ($dosya) {
                            $nitelikler[$sutun] = $this->documents->store(
                                $dosya,
                                $alan === 'document' ? DocumentStorage::IDENTITY : DocumentStorage::CIVIL_REGISTRY,
                                $reservation->user_id,
                            );
                        }
                    }

                    $mevcut = ! empty($guest['id'])
                        ? $reservation->guests()->whereKey((int) $guest['id'])->first()
                        : null;

                    if ($mevcut) {
                        $mevcut->update($nitelikler);
                        $keptIds[] = $mevcut->id;

                        continue;
                    }

                    $keptIds[] = $reservation->guests()->create($nitelikler + [
                        'age_category' => 'adult', // applyBreakdown yeniden hesaplayacak
                    ])->id;
                }

                $reservation->guests()->whereNotIn('id', $keptIds)->delete();

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
                    'surcharge' => isset($data['surcharge_per_person_day'])
                        ? (float) $data['surcharge_per_person_day']
                        : (float) $reservation->surcharge_per_person_day,
                    // Boş bırakılırsa oda kapasitesine göre otomatik hesaplanır.
                    'empty_bed_count' => isset($data['empty_bed_count']) ? (int) $data['empty_bed_count'] : null,
                    'adjustment_amount' => isset($data['adjustment_amount'])
                        ? (float) $data['adjustment_amount']
                        : (float) $reservation->adjustment_amount,
                ]);

                $this->reservations->applyBreakdown($reservation, $breakdown);
            });
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['period_id' => $e->getMessage()]);
        }

        $reservation->refresh();

        if (in_array($data['action'], ['approve', 'send_payment'], true)) {
            // Karar bekleyen başvuru: yer tahsisi yapılır ve ödemeye açılır.
            if ($reservation->status === 'pending') {
                $this->reservations->approve($reservation, Auth::user(), $data['admin_note'] ?? null);

                return redirect()->route('admin.reservations.show', $reservation)
                    ->with('success', 'Yer tahsisi yapıldı. Üyeye ödeme için açıldı.');
            }

            // Kesinleşmiş rezervasyonda kişi değişikliği fark doğurmuş olabilir.
            // balanceDue() negatife düşmediği için fark burada ham hesaplanır;
            // daha önce ödenmiş bir fazla-ödeme iadesi varsa tahsilattan düşülür.
            $oncekiIade = ($reservation->refund && $reservation->refund->isPaid())
                ? (float) $reservation->refund->amount
                : 0.0;

            $fark = round((float) $reservation->total_price - $reservation->paidTotal() + $oncekiIade, 2);

            if ($fark > 0.009) {
                // Ödeme yeniden üyeye açılır; tesiste ödeme seçimi varsa sıfırlanır
                // çünkü üyenin yeni tutarı onaylaması gerekir.
                $reservation->update(['status' => 'approved', 'collect_on_site_at' => null]);

                return redirect()->route('admin.reservations.show', $reservation)
                    ->with('success', 'Ödeme üyeye gönderildi: ' . number_format($fark, 2, ',', '.') . ' ₺ tahsil edilecek.');
            }

            if ($fark < -0.009) {
                // Fazla ödeme iadesi kendiliğinden açılır: üye panelinde
                // "iade edilecektir" görür, ödeme yapılınca yönetici işaretler.
                $this->refunds->openOverpayment($reservation, abs($fark));

                return redirect()->route('admin.reservations.show', $reservation)
                    ->with('success', 'Tutar düştü: ' . number_format(abs($fark), 2, ',', '.') . ' ₺ iade edilecek. Üye panelinde görebilir; iade yapılınca İadeler sayfasından işaretleyin.');
            }

            return redirect()->route('admin.reservations.show', $reservation)
                ->with('success', 'Değişiklikler kaydedildi. Tahsil edilecek ya da iade edilecek fark oluşmadı.');
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
            'second_room_id' => ['nullable', 'integer', 'exists:rooms,id', 'different:room_id'],
        ], [], ['room_id' => 'oda', 'second_room_id' => 'ikinci oda']);

        if (empty($data['room_id'])) {
            $reservation->update(['room_id' => null, 'second_room_id' => null]);

            return back()->with('success', 'Oda ataması kaldırıldı.');
        }

        $periodIds = array_filter([$reservation->period_id, $reservation->second_period_id]);
        $secilen = [];

        foreach (['room_id', 'second_room_id'] as $alan) {
            if (empty($data[$alan])) {
                continue;
            }

            $room = Room::find($data[$alan]);

            $uygun = $room
                && $room->facility_id === $reservation->facility_id
                && $room->room_type_id === $reservation->room_type_id
                && $room->is_active
                && Room::whereKey($room->id)->freeForPeriods($periodIds, $reservation->id)->exists();

            if (! $uygun) {
                return back()->withErrors([
                    $alan => 'Seçilen oda bu devrede uygun değil. Oda bu arada başkasına verilmiş olabilir; sayfayı yenileyin.',
                ]);
            }

            $secilen[$alan] = $room;
        }

        $reservation->update([
            'room_id' => $secilen['room_id']->id,
            'second_room_id' => $secilen['second_room_id']->id ?? null,
        ]);

        $etiket = collect($secilen)->map(fn (Room $r) => $r->label())->join(' + ');

        return back()->with('success', "{$etiket} tahsis edildi.");
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

        Reservation::where(fn ($q) => $q->whereNotNull('room_id')->orWhereNotNull('second_room_id'))
            ->whereIn('status', Room::OCCUPYING_STATUSES)
            ->where(fn ($q) => $q->whereIn('period_id', $devreler)->orWhereIn('second_period_id', $devreler))
            ->get(['id', 'room_id', 'second_room_id', 'period_id', 'second_period_id'])
            ->each(function (Reservation $r) use (&$dolu) {
                foreach (array_filter([$r->period_id, $r->second_period_id]) as $devre) {
                    foreach (array_filter([$r->room_id, $r->second_room_id]) as $odaId) {
                        $dolu[$devre][$odaId] = $r->id;
                    }
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
