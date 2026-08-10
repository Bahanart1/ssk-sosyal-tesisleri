<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ReservationController as CustomerReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('customer.dashboard');
    }

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Müşteri kimlik doğrulama
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/giris', [LoginController::class, 'show'])->name('login');
    Route::post('/giris', [LoginController::class, 'login']);
});
Route::post('/cikis', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Müşteri paneli
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:customer'])->prefix('panel')->name('customer.')->group(function () {
    Route::get('/', [CustomerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/rezervasyon/yeni', [CustomerReservationController::class, 'create'])->name('reservations.create');
    Route::post('/rezervasyon', [CustomerReservationController::class, 'store'])->name('reservations.store');
    Route::get('/rezervasyon/{reservation}', [CustomerReservationController::class, 'show'])->name('reservations.show');

    Route::get('/rezervasyon/{reservation}/odeme', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/rezervasyon/{reservation}/odeme', [PaymentController::class, 'process'])->name('payment.process');
});

/*
|--------------------------------------------------------------------------
| Admin kimlik doğrulama
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/giris', [AdminAuthController::class, 'show'])->name('login');
        Route::post('/giris', [AdminAuthController::class, 'login']);
    });
    Route::post('/cikis', [AdminAuthController::class, 'logout'])->middleware('auth')->name('logout');

    /*
    |----------------------------------------------------------------------
    | Admin paneli
    |----------------------------------------------------------------------
    */
    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/rezervasyonlar', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('/rezervasyonlar/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
        Route::post('/rezervasyonlar/{reservation}/onayla', [AdminReservationController::class, 'approve'])->name('reservations.approve');
        Route::post('/rezervasyonlar/{reservation}/reddet', [AdminReservationController::class, 'reject'])->name('reservations.reject');

        Route::get('/musteriler', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/musteriler', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/musteriler/{customer}', [CustomerController::class, 'update'])->name('customers.update');

        Route::get('/fiyatlandirma', [PricingController::class, 'index'])->name('pricing.index');
        Route::put('/fiyatlandirma/{class}', [PricingController::class, 'update'])->name('pricing.update');

        Route::get('/tesisler', [FacilityController::class, 'index'])->name('facilities.index');
        Route::post('/tesisler', [FacilityController::class, 'store'])->name('facilities.store');
        Route::put('/tesisler/{facility}', [FacilityController::class, 'update'])->name('facilities.update');
    });
});
