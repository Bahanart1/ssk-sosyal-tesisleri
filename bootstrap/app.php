<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        // Oturumlu kullanıcı /giris'e gelince / yerine role göre panele gitsin
        // (aksi halde / → /giris → / döngüsü oluşuyor)
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();

            if ($user && $user->role === 'admin') {
                return route('admin.dashboard');
            }

            return route('customer.dashboard');
        });

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
