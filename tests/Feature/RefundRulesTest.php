<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Setting;
use App\Services\RefundService;
use Carbon\Carbon;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** İptal ve iade kesintileri. */
class RefundRulesTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $refunds;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-01');
        $this->seed(SettingSeeder::class);
        $this->refunds = app(RefundService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Kaydedilmemiş, yalnız hesap için kullanılan başvuru. */
    private function reservation(string $start, float $accommodation = 30000, int $nights = 6): Reservation
    {
        return new Reservation([
            'start_date' => $start,
            'nights' => $nights,
            'accommodation_total' => $accommodation,
            'total_price' => $accommodation,
        ]);
    }

    public function test_yer_tahsis_edilemeyen_basvuruda_kesinti_yoktur(): void
    {
        $this->assertSame(0.0, $this->refunds->deductionFor($this->reservation('2026-09-06'), 'rejected'));
    }

    public function test_normal_iptalde_pesinattan_sabit_bedel_kesilir(): void
    {
        // Devre başlangıcına 36 gün var — geç iptal sayılmaz
        $this->assertSame(500.0, $this->refunds->deductionFor($this->reservation('2026-09-06'), 'cancelled'));
    }

    public function test_son_on_gunde_iptalde_konaklamanin_ucte_biri_alinir(): void
    {
        // Devre başlangıcına 5 gün kaldı
        $kesinti = $this->refunds->deductionFor($this->reservation('2026-08-06'), 'cancelled');

        $this->assertEqualsWithDelta(30000 * 0.3333, $kesinti, 1.0);
    }

    public function test_erken_ayrilista_kalinan_gun_tam_kalinmayan_yari_alinir(): void
    {
        // 6 gecelik devre 1 Ağustos'ta başladı, üye 3. günün sonunda ayrılıyor
        Carbon::setTestNow('2026-08-04');

        $kesinti = $this->refunds->deductionFor($this->reservation('2026-08-01'), 'early_departure');

        // 3 gece tam (15.000) + kalan 3 gecenin yarısı (7.500)
        $this->assertEqualsWithDelta(22500.0, $kesinti, 0.5);
    }

    public function test_kesinti_ayardan_okunur(): void
    {
        Setting::put('refund.deposit_fee', 750, 'odeme');

        $this->assertSame(750.0, $this->refunds->deductionFor($this->reservation('2026-09-06'), 'cancelled'));
    }
}
