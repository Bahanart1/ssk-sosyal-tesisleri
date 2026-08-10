<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Kullanım: ->middleware('role:admin') veya ->middleware('role:customer')
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== $role || ! $user->is_active) {
            auth()->logout();

            $loginRoute = $role === 'admin' ? 'admin.login' : 'login';

            return redirect()->route($loginRoute)
                ->withErrors(['tc_no' => 'Bu alana erişim yetkiniz bulunmuyor.']);
        }

        return $next($request);
    }
}
