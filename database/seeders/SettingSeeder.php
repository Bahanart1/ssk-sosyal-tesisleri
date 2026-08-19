<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Yönetim Kurulunca her yıl belirlenen parametreler.
 * Kaynak: sigortader.com.tr — "2026 Yılı Kamp Dönemleri ve Ücretleri" ve
 * "Kamp Konaklama Usul ve Esasları".
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- Peşinat (Madde 5/8, 8/4) ---
            ['deposit.one_period', 10000, 'pesinat', 'Bir devre peşinatı (oda/villa başına)'],
            ['deposit.two_periods', 20000, 'pesinat', 'İki devre peşinatı (oda/villa başına)'],
            ['deposit.one_period_single', 5000, 'pesinat', 'Tek kişi konaklamada bir devre peşinatı'],
            ['deposit.two_periods_single', 10000, 'pesinat', 'Tek kişi konaklamada iki devre peşinatı'],

            // --- Müracaat tarihine göre ilave ücret (kişi başı günlük) ---
            ['surcharge.tiers', [
                ['from' => '2026-02-01', 'to' => '2026-03-31', 'amount' => 0,   'label' => 'Talep toplama dönemi'],
                ['from' => '2026-04-01', 'to' => '2026-06-30', 'amount' => 200, 'label' => '01.04–30.06 arası müracaat'],
                ['from' => '2026-07-01', 'to' => null,          'amount' => 300, 'label' => '01.07 sonrası müracaat'],
            ], 'ucretlendirme', 'Müracaat tarihine göre kişi başı günlük ilave ücret'],

            // --- Çocuk ücretleri (Madde 8/5-6) ---
            ['child.free_meal_rate', 0.40, 'ucretlendirme', '0-5 yaş yemek talebi halinde uygulanacak oran'],
            ['child.discount_rate', 0.60, 'ucretlendirme', '6-11 yaş için uygulanacak oran'],

            // --- Zemin kat indirimi (Çolaklı, iki kişilik odalar) ---
            ['ground_floor.discount_rate', 0.10, 'ucretlendirme', 'Çolaklı zemin kat iki kişilik oda indirimi'],

            // --- Üyelik aidatı (Madde 5/10) ---
            ['dues.annual_amount', 200, 'aidat', 'Yıllık üyelik aidatı (varsayılan tahakkuk tutarı)'],
            ['dues.late_fee_monthly_percent', 0, 'aidat', 'Ödenmeyen aidata aylık gecikme faizi (%)'],

            // --- Ödeme ve iptal ---
            ['cancellation.min_days_before', 10, 'odeme', 'İptal için devre başlangıcına kalması gereken asgari gün'],
            ['refund.deposit_fee', 500, 'odeme', 'İade halinde peşinattan kesilen kırtasiye ve hizmet bedeli (₺)'],
            ['refund.late_cancel_ratio', 0.3333, 'odeme', 'Son günlerde sebepsiz iptalde alınan konaklama oranı (2 gün ≈ 1/3)'],
            ['refund.early_departure_ratio', 0.5, 'odeme', 'Erken ayrılışta kalınmayan günler için alınan oran'],

            // --- Talep toplama penceresi (bilgilendirme amaçlı; başvuru yıl boyu açıktır) ---
            ['application.window', ['start' => '2026-02-01', 'end' => '2026-03-31'], 'genel', 'Talep toplama dönemi'],

            // --- Banka hesapları (peşinat ve bakiye havalesi için) ---
            ['bank_accounts', [
                ['bank' => 'T. Vakıflar Bankası',  'branch' => 'Atatürk Bulvarı Şubesi (184)',  'iban' => 'TR59 0001 5001 5800 7287 9856 28'],
                ['bank' => 'T. İş Bankası',        'branch' => 'Ankara Yenişehir Şubesi (4218)', 'iban' => 'TR83 0006 4000 0014 2185 1180 47'],
                ['bank' => 'T.C. Ziraat Bankası',  'branch' => 'Ankara Mithatpaşa Şubesi (1262)', 'iban' => 'TR56 0001 0012 6243 3741 6850 01'],
                ['bank' => 'Akbank',               'branch' => 'Ankara Şubesi (5)',             'iban' => 'TR91 0004 6000 0588 8000 0219 20'],
                ['bank' => 'Garanti Bankası',      'branch' => 'Atatürk Bulvarı Şubesi (710)',  'iban' => 'TR25 0006 2000 7100 0006 2992 96'],
                ['bank' => 'Yapı Kredi Bankası',   'branch' => 'Meşrutiyet Şubesi (156)',       'iban' => 'TR09 0006 7010 0000 0067 8920 79'],
                ['bank' => 'Halkbank',             'branch' => 'Meşrutiyet Şubesi (9387)',      'iban' => 'TR24 0001 2009 3870 0016 0020 61'],
            ], 'odeme', 'Dernek banka hesapları'],
        ];

        foreach ($settings as [$key, $value, $group, $label]) {
            Setting::put($key, $value, $group, $label);
        }
    }
}
