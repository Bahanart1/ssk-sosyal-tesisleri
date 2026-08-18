<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tariff;
use Illuminate\Http\Request;

/**
 * Devrelerin yönetimi: tarihler, indirimli/indirimsiz durumu, tarife ataması
 * ve başvuruya açık olup olmadığı. Yeterli müracaat olmayan devreler Yönetim
 * Kurulunca iptal edilerek ilan edilir (Madde 4/10).
 */
class PeriodController extends Controller
{
    public function index(Request $request)
    {
        $facilities = Facility::ordered()->get();
        $facility = $facilities->firstWhere('id', (int) $request->get('facility')) ?? $facilities->first();
        $year = (int) $request->get('year', now()->year);

        $periods = Period::where('facility_id', $facility?->id)
            ->where('year', $year)
            ->with(['roomTariff', 'villaTariff'])
            ->ordered()
            ->get();

        // Devre kapatma kararı yer tahsisine bakılarak verildiğinden, onaylanmış ve
        // henüz karara bağlanmamış başvurular ayrı sayılır. Birleşik devre başvuruları
        // her iki devreye de yazılır.
        $ids = $periods->pluck('id');

        $rows = Reservation::whereIn('status', ['pending', 'approved', 'paid'])
            ->where(fn ($q) => $q->whereIn('period_id', $ids)->orWhereIn('second_period_id', $ids))
            ->get(['period_id', 'second_period_id', 'status']);

        $tally = function (array $statuses) use ($rows, $ids) {
            return $rows
                ->whereIn('status', $statuses)
                ->flatMap(fn ($r) => array_filter([$r->period_id, $r->second_period_id]))
                ->filter(fn ($id) => $ids->contains($id))
                ->countBy();
        };

        return view('admin.periods.index', [
            'facilities' => $facilities,
            'facility' => $facility,
            'year' => $year,
            'years' => Period::distinct()->orderBy('year')->pluck('year'),
            'periods' => $periods,
            'allocated' => $tally(['approved', 'paid']),
            'pending' => $tally(['pending']),
            'capacity' => $this->capacityFor($facility),
            'roomTariffs' => Tariff::where('facility_id', $facility?->id)->where('scope', 'room')->ordered()->get(),
            'villaTariffs' => Tariff::where('facility_id', $facility?->id)->where('scope', 'villa')->ordered()->get(),
        ]);
    }

    /**
     * Tesisin ünite kapasitesi.
     *
     * Fiziksel oda envanteri aktarılmışsa aktif oda sayısı esas alınır; henüz
     * aktarılmamış tesislerde oda tiplerinde tanımlı adede düşülür.
     *
     * @return array{count: int, source: string}
     */
    private function capacityFor(?Facility $facility): array
    {
        if (! $facility) {
            return ['count' => 0, 'source' => 'yok'];
        }

        $rooms = Room::where('facility_id', $facility->id)->where('is_active', true)->count();

        if ($rooms > 0) {
            return ['count' => $rooms, 'source' => 'oda envanteri'];
        }

        return [
            'count' => (int) RoomType::where('facility_id', $facility->id)->active()->sum('quantity'),
            'source' => 'oda tipi adetleri',
        ];
    }

    /**
     * Devre detayı: yer tahsis edilen ve inceleme bekleyen başvurular ile
     * devrede konaklayacak kişilerin listesi.
     */
    public function show(Period $period)
    {
        $period->load(['facility', 'roomTariff', 'villaTariff']);

        $reservations = Reservation::query()
            ->where(fn ($q) => $q->where('period_id', $period->id)->orWhere('second_period_id', $period->id))
            ->with(['user.customerGroup', 'roomType', 'room', 'guests.customerGroup', 'period', 'secondPeriod'])
            ->get()
            ->sortBy(fn ($r) => $r->user->name)
            ->values();

        $allocated = $reservations->whereIn('status', ['approved', 'paid']);
        $pending = $reservations->where('status', 'pending');
        $closed = $reservations->whereIn('status', ['rejected', 'cancelled']);

        // Oda tipi bazında tahsis / kapasite
        $roomTypes = RoomType::where('facility_id', $period->facility_id)->active()->ordered()->get()
            ->map(fn (RoomType $roomType) => [
                'roomType' => $roomType,
                'allocated' => $allocated->where('room_type_id', $roomType->id)->count(),
                'pending' => $pending->where('room_type_id', $roomType->id)->count(),
            ]);

        return view('admin.periods.show', [
            'period' => $period,
            'allocated' => $allocated,
            'pending' => $pending,
            'closed' => $closed,
            'roomTypes' => $roomTypes,
            'capacity' => $this->capacityFor($period->facility)['count'],
            'roster' => $allocated->flatMap->guests->sortBy('full_name')->values(),
            'totals' => [
                'billed' => (float) $allocated->sum('total_price'),
                'collected' => (float) $allocated->sum(fn ($r) => $r->paidTotal()),
                'outstanding' => (float) $allocated->sum(fn ($r) => $r->balanceDue()),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'number' => ['required', 'integer', 'min:1', 'max:60'],
            'start_date' => ['required', 'date'],
            'nights' => ['required', 'integer', 'min:1', 'max:30'],
            'is_discounted' => ['nullable', 'boolean'],
            'combine_group' => ['nullable', 'integer', 'min:1'],
            'room_tariff_id' => ['required', 'exists:tariffs,id'],
            'villa_tariff_id' => ['nullable', 'exists:tariffs,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'number' => 'devre no',
            'start_date' => 'başlangıç tarihi',
            'room_tariff_id' => 'oda tarifesi',
        ]);

        $exists = Period::where('facility_id', $data['facility_id'])
            ->where('year', $data['year'])
            ->where('number', $data['number'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['number' => 'Bu tesis ve yıl için aynı numaralı devre zaten tanımlı.']);
        }

        $start = \Carbon\Carbon::parse($data['start_date']);

        Period::create($data + [
            'end_date' => $start->copy()->addDays($data['nights']),
            'is_open' => true,
        ]);

        return back()->with('success', 'Devre eklendi.');
    }

    public function update(Request $request, Period $period)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'nights' => ['required', 'integer', 'min:1', 'max:30'],
            'is_discounted' => ['nullable', 'boolean'],
            'is_open' => ['nullable', 'boolean'],
            'combine_group' => ['nullable', 'integer', 'min:1'],
            'room_tariff_id' => ['required', 'exists:tariffs,id'],
            'villa_tariff_id' => ['nullable', 'exists:tariffs,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'start_date' => 'başlangıç tarihi',
            'room_tariff_id' => 'oda tarifesi',
        ]);

        $start = \Carbon\Carbon::parse($data['start_date']);

        $period->update($data + [
            'end_date' => $start->copy()->addDays($data['nights']),
            'is_discounted' => (bool) ($data['is_discounted'] ?? false),
            'is_open' => (bool) ($data['is_open'] ?? false),
        ]);

        return back()->with('success', "{$period->label()} güncellendi.");
    }

    /** Devreyi başvuruya açar/kapatır. */
    public function toggle(Period $period)
    {
        $period->update(['is_open' => ! $period->is_open]);

        return back()->with('success', "{$period->label()} " . ($period->is_open ? 'başvuruya açıldı.' : 'başvuruya kapatıldı.'));
    }
}
