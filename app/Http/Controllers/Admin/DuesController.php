<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\MembershipDue;
use App\Models\Setting;
use App\Models\User;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Üyelik aidatlarının yıl bazlı tahakkuk ve tahsilat defteri (Madde 5/10).
 */
class DuesController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $query = User::customers()
            ->whereHas('customerGroup', fn ($q) => $q->where('requires_membership', true))
            ->with([
                'customerGroup',
                'dues' => fn ($q) => $q->where('year', $year),
            ])
            ->withSum(
                ['dues as outstanding_total' => fn ($q) => $q->where('status', 'unpaid')->where('year', '<=', $year)],
                'amount'
            );

        if ($search = $request->get('q')) {
            $query->where(fn ($q) => $q
                ->where(function ($u) use ($search) {
                    foreach (SearchText::tokens($search) as $kelime) {
                        $u->where('search_index', 'like', "%{$kelime}%");
                    }
                }));
        }

        if ($group = $request->get('group')) {
            $query->where('customer_group_id', $group);
        }

        // Durum süzgeci seçilen yılın kaydına göre çalışır
        match ($request->get('status')) {
            'paid' => $query->whereHas('dues', fn ($q) => $q->where('year', $year)->where('status', 'paid')),
            'waived' => $query->whereHas('dues', fn ($q) => $q->where('year', $year)->where('status', 'waived')),
            'unpaid' => $query->whereHas('dues', fn ($q) => $q->where('year', $year)->where('status', 'unpaid')),
            'missing' => $query->whereDoesntHave('dues', fn ($q) => $q->where('year', $year)),
            default => null,
        };

        return view('admin.dues.index', [
            'year' => $year,
            'years' => $this->years($year),
            'members' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'groups' => CustomerGroup::ordered()->where('requires_membership', true)->get(),
            'summary' => $this->summary($year),
            'defaultAmount' => $this->defaultAmount($year),
            'methods' => MembershipDue::METHODS,
        ]);
    }

    /**
     * Seçilen yıl için, kaydı olmayan tüm aktif üyelere borç tahakkuku açar.
     */
    public function accrue(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'min:0'],
        ], [], ['year' => 'yıl', 'amount' => 'aidat tutarı']);

        $members = User::customers()
            ->where('is_active', true)
            ->whereHas('customerGroup', fn ($q) => $q->where('requires_membership', true))
            ->whereDoesntHave('dues', fn ($q) => $q->where('year', $data['year']))
            ->get(['id']);

        DB::transaction(function () use ($members, $data) {
            foreach ($members as $member) {
                MembershipDue::create([
                    'user_id' => $member->id,
                    'year' => $data['year'],
                    'amount' => $data['amount'],
                    'status' => 'unpaid',
                    'recorded_by' => Auth::id(),
                ]);
            }
        });

        return back()->with(
            'success',
            $members->count() > 0
                ? "{$data['year']} yılı için {$members->count()} üyeye aidat tahakkuku açıldı."
                : "{$data['year']} yılı için tahakkuku açılmamış üye bulunmuyor."
        );
    }

    /** Tek bir üye için aidat kaydı açar (üye detay ekranından). */
    public function store(Request $request, User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $data = $request->validate([
            'year' => [
                'required', 'integer', 'min:2000', 'max:2100',
                Rule::unique('membership_dues', 'year')->where('user_id', $customer->id),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
        ], [
            'year.unique' => 'Bu üye için seçilen yıla ait aidat kaydı zaten var.',
        ], ['year' => 'yıl', 'amount' => 'tutar']);

        MembershipDue::create($data + [
            'user_id' => $customer->id,
            'status' => 'unpaid',
            'recorded_by' => Auth::id(),
        ]);

        return back()->with('success', "{$data['year']} yılı aidat kaydı oluşturuldu.");
    }

    public function update(Request $request, MembershipDue $due)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['unpaid', 'paid', 'waived'])],
            'paid_at' => ['nullable', 'date', 'required_if:status,paid'],
            'method' => ['nullable', Rule::in(array_keys(MembershipDue::METHODS)), 'required_if:status,paid'],
            'receipt_no' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'paid_at.required_if' => 'Ödendi olarak işaretlemek için ödeme tarihi gerekir.',
            'method.required_if' => 'Ödendi olarak işaretlemek için ödeme yöntemi gerekir.',
        ], [
            'amount' => 'tutar',
            'paid_at' => 'ödeme tarihi',
            'method' => 'ödeme yöntemi',
            'receipt_no' => 'makbuz no',
        ]);

        // Borçlu veya muaf duruma dönüldüğünde tahsilat bilgileri temizlenir
        if ($data['status'] !== 'paid') {
            $data['paid_at'] = null;
            $data['method'] = null;
        }

        $due->update($data + ['recorded_by' => Auth::id()]);

        return back()->with('success', "{$due->year} yılı aidat kaydı güncellendi.");
    }

    /** Listeden hızlı tahsilat işaretlemesi. */
    public function markPaid(Request $request, MembershipDue $due)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(MembershipDue::METHODS))],
            'paid_at' => ['nullable', 'date'],
        ], [], ['method' => 'ödeme yöntemi']);

        // Oran sonradan değişse bile tahsilat anındaki faiz sabit kalsın.
        $faiz = $due->interestAmount();

        $due->update([
            'status' => 'paid',
            'late_fee' => $faiz,
            'method' => $data['method'],
            'paid_at' => $data['paid_at'] ?? now()->toDateString(),
            'recorded_by' => Auth::id(),
        ]);

        $mesaj = "{$due->user->name} · {$due->year} aidatı tahsil edildi olarak işaretlendi.";

        if ($faiz > 0) {
            $mesaj .= ' Gecikme faizi: ' . number_format($faiz, 2, ',', '.') . ' ₺.';
        }

        return back()->with('success', $mesaj);
    }

    public function destroy(MembershipDue $due)
    {
        $year = $due->year;
        $due->delete();

        return back()->with('success', "{$year} yılı aidat kaydı silindi.");
    }

    /**
     * Seçilen yılın tahakkuk ve tahsilat özeti.
     *
     * @return array<string, mixed>
     */
    private function summary(int $year): array
    {
        $rows = MembershipDue::where('year', $year)
            ->selectRaw('status, count(*) as adet, sum(amount) as tutar')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $amount = fn (string $status) => (float) ($rows[$status]->tutar ?? 0);
        $count = fn (string $status) => (int) ($rows[$status]->adet ?? 0);

        $accrued = $amount('unpaid') + $amount('paid') + $amount('waived');
        $collected = $amount('paid');

        return [
            'accrued' => $accrued,
            'collected' => $collected,
            'outstanding' => $amount('unpaid'),
            'waived' => $amount('waived'),
            'paid_count' => $count('paid'),
            'unpaid_count' => $count('unpaid'),
            'waived_count' => $count('waived'),
            'total_count' => $count('paid') + $count('unpaid') + $count('waived'),
            'rate' => $accrued > 0 ? $collected / $accrued : 0.0,
            'missing' => User::customers()
                ->where('is_active', true)
                ->whereHas('customerGroup', fn ($q) => $q->where('requires_membership', true))
                ->whereDoesntHave('dues', fn ($q) => $q->where('year', $year))
                ->count(),
        ];
    }

    /** @return list<int> */
    private function years(int $selected): array
    {
        $known = MembershipDue::distinct()->orderByDesc('year')->pluck('year')->all();
        $years = array_unique(array_merge($known, [(int) now()->year, (int) now()->year - 1, $selected]));

        rsort($years);

        return array_values($years);
    }

    private function defaultAmount(int $year): float
    {
        $amounts = collect(Setting::get('dues.annual_amounts', []));
        $forYear = $amounts->firstWhere('year', $year);

        return (float) ($forYear['amount'] ?? Setting::number('dues.annual_amount', 0));
    }
}
