<?php

namespace App\Providers;

use App\Models\MembershipDue;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\NestPayGateway;
use App\Services\Payment\PaymentGateway;
use App\Support\ReservationStatus;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => match (config('payment.driver')) {
            'nestpay' => new NestPayGateway,
            default => new FakeGateway,
        });
    }

    public function boot(): void
    {
        // super-admin bütün yetkileri taşır; yetki listesi tutulmaz, böylece yeni
        // bir yetki eklendiğinde super admin'e ayrıca vermeyi unutmak imkânsızdır.
        Gate::before(fn ($user) => $user->hasRole(RoleSeeder::SUPER_ADMIN) ? true : null);

        Carbon::setLocale('tr');

        // Yönetim menüsündeki bekleyen iş sayaçları
        View::composer('components.layouts.admin', function ($view) {
            $view->with('navBadges', [
                'admin.reservations.index' => Reservation::where('status', ReservationStatus::PENDING)->count(),
                'admin.payments.index' => Payment::where('status', 'pending')->where('method', 'bank_transfer')->count(),
                'admin.dues.index' => MembershipDue::unpaid()->due()->count(),
                // Havalesi yapılacak iadeler; IBAN bekleyenler yöneticinin işi değil.
                'admin.refunds.index' => Refund::payable()->count(),
            ]);
        });
    }
}
