<?php

namespace App\Providers;

use App\Models\MembershipDue;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\NestPayGateway;
use App\Services\Payment\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
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

        // Yönetim menüsündeki bekleyen iş sayaçları
        View::composer('components.layouts.admin', function ($view) {
            $view->with('navBadges', [
                'admin.reservations.index' => Reservation::where('status', 'pending')->count(),
                'admin.payments.index' => Payment::where('status', 'pending')->where('method', 'bank_transfer')->count(),
                'admin.dues.index' => MembershipDue::unpaid()->due()->count(),
            ]);
        });
    }
}
