<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\MembershipDue;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) now()->year;

        $query = User::customers()
            ->with('customerGroup')
            ->withCount('reservations')
            ->withCount(['dues as unpaid_dues_count' => fn ($q) => $q->where('status', 'unpaid')->where('year', '<=', $year)]);

        if ($search = $request->get('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('tc_no', 'like', "%{$search}%")
                ->orWhere('membership_no', 'like', "%{$search}%"));
        }

        if ($group = $request->get('group')) {
            $query->where('customer_group_id', $group);
        }

        if ($request->get('dues') === 'debt') {
            $query->whereHas('dues', fn ($q) => $q->where('status', 'unpaid')->where('year', '<=', $year));
        }

        if ($request->get('active') === 'passive') {
            $query->where('is_active', false);
        }

        return view('admin.customers.index', [
            'customers' => $query->orderBy('name')->paginate(20)->withQueryString(),
            'groups' => CustomerGroup::ordered()->get(),
        ]);
    }

    /** Üyenin tüm bilgilerini tek sayfada toplar. */
    public function show(User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $customer->load([
            'customerGroup',
            'dues.recorder',
            'reservations.facility',
            'reservations.roomType',
            'reservations.period',
            'reservations.payments',
        ]);

        $reservations = $customer->reservations->sortByDesc('created_at');
        $payments = $reservations->flatMap->payments->sortByDesc('created_at');

        return view('admin.customers.show', [
            'customer' => $customer,
            'reservations' => $reservations,
            'payments' => $payments,
            'stats' => [
                'reservations' => $reservations->count(),
                'nights' => (int) $reservations->whereIn('status', ['approved', 'paid'])->sum('nights'),
                'collected' => (float) $payments->where('status', 'success')->sum('amount'),
                'outstanding' => (float) $reservations->whereIn('status', ['pending', 'approved'])
                    ->sum(fn ($r) => $r->balanceDue()),
                'duesDebt' => $customer->duesDebtTotal(),
            ],
            'duesYear' => (int) now()->year,
            'defaultDuesAmount' => Setting::number('dues.annual_amount', 0),
            'methods' => MembershipDue::METHODS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('create', $this->rules(), [], $this->attributes());

        $customer = User::create($data + ['role' => 'customer', 'is_active' => true]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Üye hesabı oluşturuldu. Giriş bilgilerini üyeye iletebilirsiniz.');
    }

    public function update(Request $request, User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $data = $request->validateWithBag('edit', $this->rules($customer), [], $this->attributes());

        $customer->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
            'password' => filled($data['password'] ?? null) ? $data['password'] : $customer->password,
        ]);

        return back()->with('success', "{$customer->name} güncellendi.");
    }

    /** @return array<string, mixed> */
    private function rules(?User $customer = null): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'tc_no' => ['required', 'digits:11', Rule::unique('users', 'tc_no')->ignore($customer?->id)],
            'membership_no' => ['nullable', 'string', 'max:20', Rule::unique('users', 'membership_no')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'joined_at' => ['nullable', 'date'],
            'customer_group_id' => ['required', 'exists:customer_groups,id'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$customer ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'name' => 'ad soyad',
            'tc_no' => 'TC kimlik numarası',
            'membership_no' => 'üyelik numarası',
            'phone' => 'telefon',
            'email' => 'e-posta',
            'address' => 'adres',
            'joined_at' => 'üyelik tarihi',
            'customer_group_id' => 'müşteri grubu',
            'password' => 'şifre',
        ];
    }
}
