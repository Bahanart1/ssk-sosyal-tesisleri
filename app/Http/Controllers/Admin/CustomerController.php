<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'customer')->with('customerClass');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tc_no', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(12)->withQueryString();
        $classes = CustomerClass::orderBy('id')->get();

        return view('admin.customers.index', compact('customers', 'classes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tc_no' => ['required', 'digits:11', 'unique:users,tc_no'],
            'phone' => ['nullable', 'string', 'max:20'],
            'customer_class_id' => ['required', 'exists:customer_classes,id'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => $data['name'],
            'tc_no' => $data['tc_no'],
            'phone' => $data['phone'] ?? null,
            'customer_class_id' => $data['customer_class_id'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ]);

        return back()->with('success', 'Müşteri hesabı oluşturuldu.');
    }

    public function update(Request $request, User $customer)
    {
        abort_unless($customer->role === 'customer', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'customer_class_id' => ['required', 'exists:customer_classes,id'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $customer->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'customer_class_id' => $data['customer_class_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $customer->password = Hash::make($data['password']);
        }

        $customer->save();

        return back()->with('success', 'Müşteri bilgileri güncellendi.');
    }
}
