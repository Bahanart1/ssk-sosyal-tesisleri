<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerClass;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index()
    {
        $classes = CustomerClass::orderBy('id')->get();

        return view('admin.pricing.index', compact('classes'));
    }

    public function update(Request $request, CustomerClass $class)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'daily_price' => ['required', 'numeric', 'min:0'],
        ]);

        $class->update($data);

        return back()->with('success', "{$class->name} fiyatı güncellendi.");
    }
}
