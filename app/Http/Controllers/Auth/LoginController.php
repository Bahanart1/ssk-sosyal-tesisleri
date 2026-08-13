<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'tc_no' => ['required', 'digits:11'],
            'password' => ['required', 'string'],
        ], [], [
            'tc_no' => 'TC kimlik numarası',
            'password' => 'şifre',
        ]);

        if (! Auth::attempt(['tc_no' => $credentials['tc_no'], 'password' => $credentials['password'], 'role' => 'customer'], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'tc_no' => 'Girdiğiniz bilgilerle eşleşen bir hesap bulunamadı.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'tc_no' => 'Hesabınız pasif durumda. Lütfen Dernek ile iletişime geçin.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request)
    {
        $isAdmin = $request->user()?->isAdmin();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($isAdmin ? 'admin.login' : 'login');
    }
}
