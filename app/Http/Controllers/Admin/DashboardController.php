<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /** Yer tahsisi sürecinde sayılan başvuru durumları. */
    private const LIVE_STATUSES = ['pending', 'approved', 'paid'];

    public function index()
    {
        return view('admin.dashboard', [
            'hero' => $this->collections(),
            'counts' => $this->counts(),
            'revenue' => $this->monthlyRevenue(),
            'statusMix' => $this->statusMix(),
            'occupancy' => $this->occupancy(),
            'groupMix' => $this->groupMix(),
            'roomMix' => $this->roomMix(),
            'recent' => Reservation::with(['user', 'facility', 'roomType', 'period'])->latest()->take(6)->get(),
            'pendingReceipts' => Payment::with('reservation.user')
                ->where('status', 'pending')->where('method', 'bank_transfer')
                ->latest()->take(5)->get(),
        ]);
    }

    /**
     * Toplam tahsilat, önceki 30 güne göre değişim ve 12 haftalık seyir.
     *
     * @return array<string, mixed>
     */
    private function collections(): array
    {
        $payments = Payment::query()
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->get(['amount', 'paid_at']);

        $since = fn (Carbon $from, ?Carbon $to = null) => (float) $payments
            ->filter(fn ($p) => $p->paid_at->gte($from) && ($to === null || $p->paid_at->lt($to)))
            ->sum('amount');

        $last30 = $since(now()->subDays(30));
        $prev30 = $since(now()->subDays(60), now()->subDays(30));

        $change = $prev30 > 0 ? (($last30 - $prev30) / $prev30) * 100 : null;

        // 12 haftalık kıvılcım çizgisi
        $spark = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = now()->copy()->subWeeks($i)->startOfWeek();
            $spark[] = $since($start, $start->copy()->addWeek());
        }

        return [
            'total' => (float) $payments->sum('amount'),
            'last30' => $last30,
            'delta' => $change === null ? null : [
                'text' => '%' . number_format(abs($change), 0, ',', '.'),
                'positive' => $change >= 0,
                'period' => 'önceki 30 güne göre',
            ],
            'spark' => $spark,
        ];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $byStatus = Reservation::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'approved' => (int) ($byStatus['approved'] ?? 0),
            'paid' => (int) ($byStatus['paid'] ?? 0),
            'rejected' => (int) ($byStatus['rejected'] ?? 0),
            'cancelled' => (int) ($byStatus['cancelled'] ?? 0),
            'total' => (int) $byStatus->sum(),
            'customers' => User::where('role', 'customer')->count(),
            'duesDebt' => User::where('role', 'customer')
                ->whereHas('dues', fn ($q) => $q->where('status', 'unpaid')->where('year', '<=', now()->year))
                ->count(),
            'receipts' => Payment::where('status', 'pending')->where('method', 'bank_transfer')->count(),
        ];
    }

    /**
     * Son 6 ayın aylık tahsilatı.
     *
     * @return list<array{label: string, value: float, display: string}>
     */
    private function monthlyRevenue(): array
    {
        $from = now()->copy()->subMonths(5)->startOfMonth();

        $payments = Payment::query()
            ->where('status', 'success')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'));

        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $from->copy()->addMonths($i);
            $total = (float) ($payments[$month->format('Y-m')] ?? collect())->sum('amount');

            $points[] = [
                'label' => $month->translatedFormat('M'),
                'value' => $total,
                'display' => '₺' . number_format($total, 0, ',', '.'),
            ];
        }

        return $points;
    }

    /** @return list<array<string, mixed>> */
    private function statusMix(): array
    {
        $counts = $this->counts();
        $total = max(1, $counts['total']);

        $map = [
            ['pending', 'İnceleniyor', 'amber'],
            ['approved', 'Yer tahsis edildi', 'teal'],
            ['paid', 'Ödendi', 'green'],
            ['rejected', 'Reddedildi', 'red'],
            ['cancelled', 'İptal edildi', 'gray'],
        ];

        return array_map(fn ($row) => [
            'label' => $row[1],
            'value' => $counts[$row[0]],
            'share' => $counts[$row[0]] / $total,
            'tone' => $row[2],
            'href' => route('admin.reservations.index', ['status' => $row[0]]),
        ], $map);
    }

    /**
     * Tesis bazında yaklaşan devrelerin doluluğu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function occupancy(): Collection
    {
        $bookings = Reservation::query()
            ->whereIn('status', self::LIVE_STATUSES)
            ->get(['period_id', 'second_period_id'])
            ->flatMap(fn ($r) => array_filter([$r->period_id, $r->second_period_id]))
            ->countBy();

        return Facility::active()->ordered()->get()->map(function (Facility $facility) use ($bookings) {
            $capacity = (int) RoomType::where('facility_id', $facility->id)->active()->sum('quantity');

            $periods = Period::where('facility_id', $facility->id)
                ->open()->upcoming()->ordered()->take(10)->get();

            return [
                'facility' => $facility,
                'capacity' => $capacity,
                'columns' => $periods->map(function (Period $period) use ($bookings, $capacity) {
                    $count = (int) ($bookings[$period->id] ?? 0);

                    return [
                        'label' => (string) $period->number,
                        'value' => $count,
                        'display' => $count . ' başvuru' . ($capacity > 0 ? ' · %' . round(($count / $capacity) * 100) . ' doluluk' : ''),
                        'meta' => $period->number . '. Devre · ' . $period->start_date->translatedFormat('d M') . ' – ' . $period->end_date->translatedFormat('d M'),
                        'href' => route('admin.periods.show', $period),
                    ];
                })->all(),
            ];
        });
    }

    /**
     * Konaklayan kişilerin müşteri grubu dağılımı.
     * Gruplar sıralı bir ölçek olduğundan tek hue'lu rampayla gösterilir.
     *
     * @return list<array<string, mixed>>
     */
    private function groupMix(): array
    {
        $counts = ReservationGuest::query()
            ->whereHas('reservation', fn ($q) => $q->whereIn('status', self::LIVE_STATUSES))
            ->selectRaw('customer_group_id, count(*) as total')
            ->groupBy('customer_group_id')
            ->pluck('total', 'customer_group_id');

        $total = max(1, (int) $counts->sum());

        return CustomerGroup::ordered()->get()->map(fn (CustomerGroup $group) => [
            'label' => $group->name,
            'value' => (int) ($counts[$group->id] ?? 0),
            'display' => (int) ($counts[$group->id] ?? 0) . ' kişi',
            'meta' => $group->description,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function roomMix(): array
    {
        $counts = Reservation::query()
            ->whereIn('status', self::LIVE_STATUSES)
            ->selectRaw('room_type_id, count(*) as total')
            ->groupBy('room_type_id')
            ->pluck('total', 'room_type_id');

        $total = max(1, (int) $counts->sum());
        $roomTypes = RoomType::with('facility')->active()->ordered()->get();

        // Aynı oda adı iki tesiste de geçebildiğinden, çakışanlara tesis adı eklenir.
        $duplicates = $roomTypes->groupBy('name')->filter(fn ($g) => $g->count() > 1)->keys();

        return $roomTypes
            ->map(function (RoomType $roomType) use ($counts, $total, $duplicates) {
                $value = (int) ($counts[$roomType->id] ?? 0);

                return [
                    'label' => $duplicates->contains($roomType->name)
                        ? $roomType->name . ' · ' . Str::before($roomType->facility->name, ' ')
                        : $roomType->name,
                    'value' => $value,
                    'display' => (string) $value,
                    'meta' => $roomType->facility->name . ' · %' . round(($value / $total) * 100) . ' pay',
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }
}
