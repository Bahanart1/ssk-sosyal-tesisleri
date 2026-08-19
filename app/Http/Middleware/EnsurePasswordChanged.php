<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Şifresi hâlâ TC kimlik numarası olan üye, şifresini değiştirene kadar
 * paneldeki hiçbir sayfaya giremez; şifre değiştirme ekranına yönlendirilir.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->must_change_password
            && ! $request->routeIs('customer.password.force', 'customer.password.force.update', 'logout')) {
            return redirect()->route('customer.password.force');
        }

        return $next($request);
    }
}
