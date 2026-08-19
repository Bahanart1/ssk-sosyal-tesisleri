<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DuesController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\OnSiteCollectionController;
use App\Http\Controllers\Admin\PetitionController as AdminPetitionController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TariffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\DuesController as CustomerDuesController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Customer\ForcedPasswordController as CustomerPasswordController;
use App\Http\Controllers\Customer\PetitionController as CustomerPetitionController;
use App\Http\Controllers\Customer\RefundController as CustomerRefundController;
use App\Http\Controllers\Customer\ReservationController as CustomerReservationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PaymentFlowController;
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
| Üye kimlik doğrulama
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/giris', [LoginController::class, 'show'])->name('login');
    Route::post('/giris', [LoginController::class, 'login']);
});
Route::post('/cikis', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Sanal POS dönüşü
|--------------------------------------------------------------------------
| Banka, 3D Secure sonucunu kullanıcının tarayıcısı üzerinden POST eder.
| Çapraz site istek olduğu için oturum ve CSRF koşulu aranmaz; doğrulama
| bankanın imzasıyla yapılır (bkz. NestPayGateway::verifyHash).
*/
Route::match(['get', 'post'], '/odeme/{payment}/sonuc', [PaymentFlowController::class, 'callback'])
    ->name('payment.callback');
Route::get('/odeme/{payment}/simulasyon', [PaymentFlowController::class, 'simulate'])
    ->middleware('auth')
    ->name('payment.simulate');

/*
|--------------------------------------------------------------------------
| Belgeler — kimlik, dekont ve sağlık raporu
|--------------------------------------------------------------------------
| Kişisel veri içerdiği için her istekte yetki kontrolünden geçer.
*/
Route::middleware('auth')->prefix('belge')->name('documents.')->group(function () {
    Route::get('/kimlik/{guest}', [DocumentController::class, 'identity'])->name('identity');
    Route::get('/dekont/{payment}', [DocumentController::class, 'receipt'])->name('receipt');
    Route::get('/rapor/{reservation}', [DocumentController::class, 'healthReport'])->name('health-report');
    Route::get('/nufus-kayit/{guest}', [DocumentController::class, 'civilRegistry'])->name('civil-registry');
    Route::get('/dilekce/{petition}', [DocumentController::class, 'petition'])->name('petition');
    Route::get('/aidat-dekont/{due}', [DocumentController::class, 'duesReceipt'])->name('dues-receipt');
});

/*
|--------------------------------------------------------------------------
| Üye paneli
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:customer', 'password.changed'])->prefix('panel')->name('customer.')->group(function () {
    // Zorunlu şifre değişikliği: middleware bu iki rotayı muaf tutar.
    Route::get('/sifre-belirle', [CustomerPasswordController::class, 'show'])->name('password.force');
    Route::post('/sifre-belirle', [CustomerPasswordController::class, 'update'])->name('password.force.update');

    Route::get('/', [CustomerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/aidatlarim', [CustomerDuesController::class, 'index'])->name('dues.index');

    Route::get('/hesabim', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/hesabim', [CustomerProfileController::class, 'update'])->name('profile.update');
    Route::put('/hesabim/sifre', [CustomerProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/basvurularim', [CustomerReservationController::class, 'index'])->name('reservations.index');
    Route::get('/basvuru/yeni', [CustomerReservationController::class, 'create'])->name('reservations.create');
    Route::post('/basvuru/fiyat-hesapla', [CustomerReservationController::class, 'quote'])->name('reservations.quote');
    Route::post('/basvuru', [CustomerReservationController::class, 'store'])->name('reservations.store');
    Route::get('/basvuru/{reservation}', [CustomerReservationController::class, 'show'])->name('reservations.show');
    Route::post('/basvuru/{reservation}/iptal', [CustomerReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/basvuru/{reservation}/iade-talebi', [CustomerRefundController::class, 'request'])->name('refunds.request');
    Route::put('/iade/{refund}', [CustomerRefundController::class, 'update'])->name('refunds.update');
    Route::get('/dilekcelerim', [CustomerPetitionController::class, 'index'])->name('petitions.index');
    Route::view('/kvkk', 'legal.kvkk')->name('kvkk');
    Route::post('/aidatlarim/{due}/havale', [CustomerDuesController::class, 'payTransfer'])->name('dues.pay-transfer');
    Route::post('/dilekcelerim', [CustomerPetitionController::class, 'store'])->name('petitions.store');

    Route::get('/basvuru/{reservation}/odeme', [CustomerPaymentController::class, 'show'])->name('payment.show');
    Route::post('/basvuru/{reservation}/odeme/kart', [CustomerPaymentController::class, 'card'])->name('payment.card');
    Route::post('/basvuru/{reservation}/odeme/tesiste', [CustomerPaymentController::class, 'onSite'])->name('payment.on-site');
    Route::post('/basvuru/{reservation}/odeme/havale', [CustomerPaymentController::class, 'transfer'])->name('payment.transfer');

    /*
     * Yukarıdakiler yalnızca form gönderimiyle çalışan eylemlerdir. Üye geri/yenile
     * yaptığında ya da adresi doğrudan açtığında "405" hata sayfası görmesin diye
     * aynı adresler GET ile ödeme ekranına döndürülür.
     */
    Route::get('/basvuru/{reservation}/odeme/{eylem}', fn ($reservation) => redirect()
        ->route('customer.payment.show', $reservation))
        ->whereIn('eylem', ['kart', 'tesiste', 'havale'])
        ->name('payment.redirect');

    Route::get('/basvuru/{reservation}/iade-talebi', fn ($reservation) => redirect()
        ->route('customer.reservations.show', $reservation))
        ->name('refunds.request.redirect');
});

/*
|--------------------------------------------------------------------------
| Yönetim paneli
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/giris', [AdminAuthController::class, 'show'])->name('login');
        Route::post('/giris', [AdminAuthController::class, 'login']);
    });
    Route::post('/cikis', [AdminAuthController::class, 'logout'])->middleware('auth')->name('logout');

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Başvurular
        Route::get('/basvurular', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('/basvuru-olustur', [AdminReservationController::class, 'create'])->name('reservations.create');
        Route::post('/basvuru-olustur', [AdminReservationController::class, 'store'])->name('reservations.store');
        Route::get('/basvurular/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
        Route::get('/basvurular/{reservation}/duzenle', [AdminReservationController::class, 'edit'])->name('reservations.edit');
        Route::put('/basvurular/{reservation}', [AdminReservationController::class, 'update'])->name('reservations.update');
        Route::post('/basvurular/{reservation}/oda', [AdminReservationController::class, 'assignRoom'])->name('reservations.assign-room');
        Route::post('/basvurular/{reservation}/onayla', [AdminReservationController::class, 'approve'])->name('reservations.approve');
        Route::post('/basvurular/{reservation}/reddet', [AdminReservationController::class, 'reject'])->name('reservations.reject');
        Route::post('/basvurular/{reservation}/iptal', [AdminReservationController::class, 'cancel'])->name('reservations.cancel');

        // Ödemeler
        Route::get('/odemeler', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('/odemeler/{payment}/dogrula', [AdminPaymentController::class, 'verify'])->name('payments.verify');
        Route::post('/odemeler/{payment}/reddet', [AdminPaymentController::class, 'reject'])->name('payments.reject');

        // Devreler
        Route::get('/devreler', [PeriodController::class, 'index'])->name('periods.index');
        Route::post('/devreler', [PeriodController::class, 'store'])->name('periods.store');
        Route::get('/devre-ayarlari', [PeriodController::class, 'settings'])->name('periods.settings');
        Route::put('/devre-ayarlari', [PeriodController::class, 'saveSettings'])->name('periods.settings.save');
        Route::get('/devreler/{period}', [PeriodController::class, 'show'])->name('periods.show');
        Route::put('/devreler/{period}', [PeriodController::class, 'update'])->name('periods.update');
        Route::post('/devreler/{period}/durum', [PeriodController::class, 'toggle'])->name('periods.toggle');

        // Tarifeler
        Route::get('/tarifeler', [TariffController::class, 'index'])->name('tariffs.index');
        Route::post('/tarifeler', [TariffController::class, 'store'])->name('tariffs.store');
        Route::put('/tarifeler/{tariff}', [TariffController::class, 'update'])->name('tariffs.update');

        // Tesisler ve oda tipleri
        Route::get('/tesisler', [FacilityController::class, 'index'])->name('facilities.index');
        Route::post('/tesisler', [FacilityController::class, 'store'])->name('facilities.store');
        Route::put('/tesisler/{facility}', [FacilityController::class, 'update'])->name('facilities.update');
        Route::post('/tesisler/{facility}/oda-tipleri', [FacilityController::class, 'storeRoomType'])->name('room-types.store');
        Route::put('/oda-tipleri/{roomType}', [FacilityController::class, 'updateRoomType'])->name('room-types.update');

        // Oda envanteri
        Route::get('/tesiste-tahsilat', [OnSiteCollectionController::class, 'index'])->name('on-site.index');
        Route::post('/tesiste-tahsilat/{reservation}', [OnSiteCollectionController::class, 'collect'])->name('on-site.collect');
        Route::get('/iadeler', [AdminRefundController::class, 'index'])->name('refunds.index');
        Route::post('/iadeler/{refund}/ode', [AdminRefundController::class, 'pay'])->name('refunds.pay');
        Route::get('/dilekceler', [AdminPetitionController::class, 'index'])->name('petitions.index');
        Route::post('/dilekceler/{petition}/yanit', [AdminPetitionController::class, 'reply'])->name('petitions.reply');

        Route::get('/odalar', [RoomController::class, 'index'])->name('rooms.index');
        Route::put('/odalar/{room}', [RoomController::class, 'update'])->name('rooms.update');

        // Üyeler
        Route::get('/uyeler', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/uyeler', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/uyeler/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/uyeler/{customer}', [CustomerController::class, 'update'])->name('customers.update');

        // Aidatlar
        Route::get('/aidatlar', [DuesController::class, 'index'])->name('dues.index');
        Route::post('/aidatlar/tahakkuk', [DuesController::class, 'accrue'])->name('dues.accrue');
        Route::post('/uyeler/{customer}/aidat', [DuesController::class, 'store'])->name('dues.store');
        Route::put('/aidatlar/{due}', [DuesController::class, 'update'])->name('dues.update');
        Route::post('/aidatlar/{due}/tahsil', [DuesController::class, 'markPaid'])->name('dues.paid');
        Route::delete('/aidatlar/{due}', [DuesController::class, 'destroy'])->name('dues.destroy');

        // Parametreler
        Route::get('/parametreler', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/parametreler', [SettingController::class, 'update'])->name('settings.update');
    });
});
