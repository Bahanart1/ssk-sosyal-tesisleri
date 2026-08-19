<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\RefundService;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * İade takibi. Başvuru sayısı yükseldikçe red sayısı da yükseleceği için
 * iadeler ayrı bir iş listesi olarak yürütülür: IBAN bekleyenler, havalesi
 * yapılacaklar ve tamamlananlar.
 */
class RefundController extends Controller
{
    private const STATUSES = [
        'pending' => 'Ödeme bekliyor',
        'awaiting_iban' => 'IBAN bekliyor',
        'paid' => 'İade edildi',
    ];

    public function __construct(private readonly RefundService $refunds) {}

    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->get('status'), self::STATUSES)
            ? $request->get('status')
            : 'pending';

        // Peşinatlar: reddedilen/iptal edilen başvuruların iadeleri.
        // Fazla ödemeler: kişi değişikliği sonrası oluşan farklar.
        $tur = $request->get('tur') === 'fazla' ? 'fazla' : 'pesinat';

        $turKosulu = fn ($q) => $tur === 'fazla'
            ? $q->where('reason', 'overpayment')
            : $q->whereIn('reason', ['rejected', 'cancelled']);

        $query = Refund::with(['user', 'reservation.facility', 'reservation.period', 'processor'])
            ->where('status', $status)
            ->where($turKosulu);

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u
                    ->where(function ($u) use ($search) {
                        foreach (SearchText::tokens($search) as $kelime) {
                            $u->where('search_index', 'like', "%{$kelime}%");
                        }
                    }))
                    ->orWhereHas('reservation', fn ($r) => $r->where('code', 'like', "%{$search}%"));
            });
        }

        return view('admin.refunds.index', [
            'refunds' => $query->latest()->paginate(20)->withQueryString(),
            'statuses' => self::STATUSES,
            'status' => $status,
            'tur' => $tur,
            'counts' => Refund::selectRaw('status, count(*) as adet, sum(amount) as tutar')
                ->where($turKosulu)
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
            'turCounts' => [
                'pesinat' => Refund::whereIn('reason', ['rejected', 'cancelled'])->open()->count(),
                'fazla' => Refund::where('reason', 'overpayment')->open()->count(),
            ],
        ]);
    }

    public function pay(Request $request, Refund $refund)
    {
        abort_if($refund->isPaid(), 422);
        // Fazla ödeme iadesi taraflar arasında yapılır; IBAN bildirimi beklenmez.
        abort_if($refund->iban === null && $refund->reason !== 'overpayment', 422, 'Üye henüz hesap bilgisi bildirmedi.');

        $data = $request->validate([
            'reference_no' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['reference_no' => 'havale referansı']);

        $this->refunds->markPaid($refund, Auth::user(), $data['reference_no'] ?? null, $data['note'] ?? null);

        return back()->with('success', "{$refund->user->name} için iade ödendi olarak işaretlendi.");
    }
}
