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
        if (Auth::check() && Auth::user()->isCustomer()) {
            return redirect()->route('customer.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'tc_no' => ['required', 'digits:11'],
            'password' => ['required'],
        ], [
            'tc_no.digits' => 'TC Kimlik No 11 haneli olmalıdır.',
        ]);

        $user = \App\Models\User::where('tc_no', $credentials['tc_no'])
            ->where('role', 'customer')
            ->first();

        if (! $user || ! $user->is_active || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'tc_no' => 'TC Kimlik No veya şifre hatalı.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
