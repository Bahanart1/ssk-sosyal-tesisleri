<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('customer.profile.edit', [
            'member' => Auth::user()->load('customerGroup'),
        ]);
    }

    /**
     * Üye yalnızca iletişim bilgilerini güncelleyebilir.
     * Müşteri grubu, üyelik numarası, TC kimlik numarası ve aidat kayıtları
     * Dernek tarafından yönetilir; bu uçtan değiştirilemez.
     */
    public function update(Request $request)
    {
        $member = Auth::user();

        $data = $request->validateWithBag('profile', [
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')->ignore($member->id)],
            'address' => ['nullable', 'string', 'max:500'],
        ], [], [
            'phone' => 'telefon',
            'email' => 'e-posta',
            'address' => 'adres',
        ]);

        $member->update($data);

        return back()->with('success', 'İletişim bilgileriniz güncellendi.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'Yeni şifre tekrarı eşleşmiyor.',
            'password.min' => 'Yeni şifre en az 8 karakter olmalıdır.',
        ], [
            'current_password' => 'mevcut şifre',
            'password' => 'yeni şifre',
        ]);

        if (! Hash::check($data['current_password'], Auth::user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mevcut şifreniz hatalı.',
            ])->errorBag('password');
        }

        Auth::user()->update(['password' => $data['password']]);

        return back()->with('success', 'Şifreniz güncellendi.');
    }
}
