<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Period;
use App\Models\Reservation;
use App\Services\PaymentService;
use App\Support\ReservationStatus;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tesiste tahsil edilecek bakiyeler.
 *
 * Üye bakiyeyi girişte ödemeyi seçtiğinde rezervasyon kesinleşir ama para
 * tahsil edilmemiş olur. Tesis görevlisinin girişte kimden ne kadar alacağını
 * görmesi ve tahsilatı işlemesi için ayrı bir liste tutulur.
 */
class OnSiteCollectionController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        $tahsilEdildi = $request->get('durum') === 'collected';

        $query = Reservation::query()
            ->with(['user', 'facility', 'roomType', 'room', 'secondRoom', 'period', 'secondPeriod'])
            ->whereNotNull('collect_on_site_at')
            ->where('status', '!=', ReservationStatus::CANCELLED);

        // Tahsil edilmemiş = hâlâ bakiyesi olan kayıtlar.
        $tahsilEdildi
            ? $query->where('status', ReservationStatus::PAID)
            : $query->where('status', ReservationStatus::APPROVED);

        if ($facility = $request->get('tesis')) {
            $query->where('facility_id', $facility);
        }

        if ($period = $request->get('devre')) {
            $query->where(fn ($q) => $q->where('period_id', $period)->orWhere('second_period_id', $period));
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        foreach (SearchText::tokens($search) as $kelime) {
                            $u->where('search_index', 'like', "%{$kelime}%");
                        }
                    });
            });
        }

        $reservations = $query->orderBy('start_date')->get();

        $periods = Period::with('facility')->ordered()->get();

        return view('admin.on-site.index', [
            'reservations' => $reservations,
            'collected' => $tahsilEdildi,
            'facilities' => Facility::ordered()->get(),
            'periodsByFacility' => $periods->groupBy(fn (Period $p) => $p->facility->name),
            // Toplamlar giriş öncesi kasa planlaması için tesis bazında da verilir.
            'total' => $reservations->sum(fn (Reservation $r) => $tahsilEdildi ? $r->onSiteCollected() : $r->balanceDue()),
            'byFacility' => $reservations
                ->groupBy(fn (Reservation $r) => $r->facility->name)
                ->map(fn ($grup) => [
                    'adet' => $grup->count(),
                    'tutar' => $grup->sum(fn (Reservation $r) => $tahsilEdildi ? $r->onSiteCollected() : $r->balanceDue()),
                ]),
            'pendingCount' => Reservation::whereNotNull('collect_on_site_at')->where('status', 'approved')->count(),
        ]);
    }

    /** Tesiste para alındı; bekleyen ödeme kaydı tamamlanır. */
    public function collect(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->collectsOnSite() && $reservation->status === 'approved', 422);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['note' => 'not']);

        $payment = $reservation->payments()
            ->where('method', 'on_site')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $payment) {
            // Tutar arada değiştiyse kayıt yoksa yeniden oluşturulur.
            $payment = $this->payments->recordOnSite($reservation, 'balance', $reservation->balanceDue());
        }

        // Bakiye arada değişmiş olabilir (kişi eklendi/çıkarıldı).
        $payment->update(['amount' => $reservation->balanceDue()]);

        $this->payments->verifyTransfer($payment, Auth::user());

        if ($data['note'] ?? null) {
            $reservation->update([
                'admin_note' => trim(($reservation->admin_note ? $reservation->admin_note."\n" : '')
                    .'Tesiste tahsilat: '.$data['note']),
            ]);
        }

        return back()->with('success', "{$reservation->code} için tahsilat işlendi.");
    }
}
