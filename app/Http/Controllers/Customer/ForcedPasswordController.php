<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * İlk girişte zorunlu şifre belirleme. Yalnızca şifresi hâlâ TC kimlik
 * numarası olan üyelere gösterilir; şifre değişmeden panel açılmaz.
 */
class ForcedPasswordController extends Controller
{
    public function show()
    {
        // Bayrağı olmayan üyenin bu ekranda işi yok.
        if (! Auth::user()->must_change_password) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.password.force');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'Yeni şifreniz en az 8 karakter olmalıdır.',
            'password.confirmed' => 'Şifre tekrarı eşleşmiyor.',
        ], ['password' => 'yeni şifre']);

        // Yeni şifre yine TC olamaz; kural bunu engellemek için var.
        if (hash_equals((string) $user->tc_no, $request->input('password'))) {
            return back()->withErrors(['password' => 'Yeni şifreniz TC kimlik numaranızla aynı olamaz.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        return redirect()->route('customer.dashboard')
            ->with('success', 'Şifreniz güncellendi. Hesabınız artık daha güvenli.');
    }
}
