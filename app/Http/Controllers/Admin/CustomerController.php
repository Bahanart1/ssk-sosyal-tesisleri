<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'customer')->with('customerGroup')->withCount('reservations');

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
            $query->where(fn ($q) => $q->whereNull('dues_paid_year')->orWhere('dues_paid_year', '<', now()->year));
        }

        return view('admin.customers.index', [
            'customers' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'groups' => CustomerGroup::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('create', [
            'name' => ['required', 'string', 'max:160'],
            'tc_no' => ['required', 'digits:11', 'unique:users,tc_no'],
            'membership_no' => ['nullable', 'string', 'max:20', 'unique:users,membership_no'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'customer_group_id' => ['required', 'exists:customer_groups,id'],
            'dues_paid_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'password' => ['required', 'string', 'min:6'],
        ], [], $this->attributes());

        User::create($data + ['role' => 'customer', 'is_active' => true]);

        return back()->with('success', 'Üye hesabı oluşturuldu. Giriş bilgilerini üyeye iletebilirsiniz.');
    }

    public function update(Request $request, User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $data = $request->validateWithBag('edit', [
            'name' => ['required', 'string', 'max:160'],
            'tc_no' => ['required', 'digits:11', Rule::unique('users', 'tc_no')->ignore($customer->id)],
            'membership_no' => ['nullable', 'string', 'max:20', Rule::unique('users', 'membership_no')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'customer_group_id' => ['required', 'exists:customer_groups,id'],
            'dues_paid_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ], [], $this->attributes());

        $customer->update([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'password' => filled($data['password'] ?? null) ? $data['password'] : $customer->password,
        ]);

        return back()->with('success', "{$customer->name} güncellendi.");
    }

    /** Aidatı ödenmiş olarak işaretler (Madde 5/10). */
    public function markDuesPaid(User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $customer->update(['dues_paid_year' => now()->year]);

        return back()->with('success', "{$customer->name} için " . now()->year . ' yılı aidatı ödenmiş olarak işaretlendi.');
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
            'customer_group_id' => 'müşteri grubu',
            'dues_paid_year' => 'aidatın ödendiği yıl',
            'password' => 'şifre',
        ];
    }
}
