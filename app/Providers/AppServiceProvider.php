<?php

namespace App\Providers;

use App\Services\Payment\FakeGateway;
use App\Services\Payment\NestPayGateway;
use App\Services\Payment\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => match (config('payment.driver')) {
            'nestpay' => new NestPayGateway(),
            default => new FakeGateway(),
        });
    }

    public function boot(): void
    {
        Carbon::setLocale('tr');
    }
}
