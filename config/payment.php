<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sanal POS sürücüsü
    |--------------------------------------------------------------------------
    |
    | "fake"    → uygulama içi simülasyon ekranı (banka bilgileri girilmeden test için)
    | "nestpay" → NestPay/EST altyapılı banka sanal POS'u (Akbank, İş Bankası,
    |             Ziraat Bankası, Halkbank)
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Taksit seçenekleri
    |--------------------------------------------------------------------------
    |
    | Bakiye tutarı peşin veya banka kartına taksitle ödenebilir (Madde 8/8).
    | Vade farkı oranları taksit sayısına göre tanımlanır (0 = vade farksız).
    |
    */

    'installments' => [1, 3, 6, 9],

    'installment_rates' => [
        1 => 0.00,
        3 => 0.00,
        6 => 0.00,
        9 => 0.00,
    ],

    /* Peşinat kartla ödendiğinde taksit yapılmaz. */
    'deposit_installments_allowed' => false,

    /*
    |--------------------------------------------------------------------------
    | NestPay / EST yapılandırması
    |--------------------------------------------------------------------------
    */

    'nestpay' => [
        'url' => env('NESTPAY_URL'),
        'client_id' => env('NESTPAY_CLIENT_ID'),
        'store_key' => env('NESTPAY_STORE_KEY'),
        'store_type' => env('NESTPAY_STORE_TYPE', '3d_pay_hosting'),
    ],

];
