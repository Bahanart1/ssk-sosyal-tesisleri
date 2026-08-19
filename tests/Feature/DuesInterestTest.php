<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\MembershipDue;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Aidat gecikme faizi: oran ayarlardan okunur, yıl bitince ay başına işler. */
class DuesInterestTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-19');
        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class]);

        $this->member = User::create([
            'name' => 'Borçlu Üye', 'tc_no' => '10000000066',
            'password' => Hash::make('sifre123'), 'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'), 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Yönetici', 'email' => 'y@example.test',
            'password' => Hash::make('sifre123'), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function borc(int $year, float $amount = 200): MembershipDue
    {
        return MembershipDue::create([
            'user_id' => $this->member->id, 'year' => $year,
            'amount' => $amount, 'status' => 'unpaid',
        ]);
    }

    public function test_faiz_yil_bitince_ay_basina_isler(): void
    {
        Setting::put('dues.late_fee_monthly_percent', 3, 'aidat');

        // 2025 aidatı: vade 1 Ocak 2026, bugün 19 Ağustos 2026 → 7 tam ay
        $due = $this->borc(2025);

        $this->assertSame(7, $due->lateMonths());
        $this->assertSame(42.0, $due->interestAmount()); // 200 × %3 × 7
        $this->assertSame(242.0, $due->totalDue());
    }

    public function test_icinde_bulunulan_yila_faiz_islemez(): void
    {
        Setting::put('dues.late_fee_monthly_percent', 3, 'aidat');

        $due = $this->borc(2026);

        $this->assertSame(0, $due->lateMonths());
        $this->assertSame(0.0, $due->interestAmount());
    }

    public function test_oran_sifirsa_faiz_uygulanmaz(): void
    {
        Setting::put('dues.late_fee_monthly_percent', 0, 'aidat');

        $this->assertSame(0.0, $this->borc(2023)->interestAmount());
    }

    public function test_borc_toplami_faizi_icerir(): void
    {
        Setting::put('dues.late_fee_monthly_percent', 3, 'aidat');

        $this->borc(2025); // 200 + 42
        $this->borc(2026); // 200, faizsiz

        $this->assertSame(442.0, $this->member->duesDebtTotal());
        $this->assertSame(42.0, $this->member->duesInterestTotal());
    }

    public function test_tahsilatta_faiz_kayda_gecer_ve_oran_degisse_de_sabit_kalir(): void
    {
        Setting::put('dues.late_fee_monthly_percent', 3, 'aidat');
        $due = $this->borc(2025);

        $this->actingAs($this->admin)
            ->post(route('admin.dues.paid', $due), ['method' => 'cash'])
            ->assertSessionHasNoErrors();

        $due->refresh();

        $this->assertSame('paid', $due->status);
        $this->assertSame('42.00', $due->late_fee);

        // Oran sonradan değişirse ödenmiş kaydın faizi değişmez
        Setting::put('dues.late_fee_monthly_percent', 10, 'aidat');
        $this->assertSame(42.0, $due->fresh()->interestAmount());
    }

    public function test_uye_faizi_sayfasinda_gorur(): void
    {
        Setting::put('dues.late_fee_monthly_percent', 3, 'aidat');
        $this->borc(2025);

        $this->actingAs($this->member)
            ->get(route('customer.dues.index'))
            ->assertOk()
            ->assertSee('Gecikme faizi')
            ->assertSee('7 ay');
    }

    public function test_uye_dekont_yukleyerek_aidat_oder_ve_yonetici_onaylar(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Services\DocumentStorage::DISK);
        Setting::put('dues.late_fee_monthly_percent', 3, 'aidat');

        $due = $this->borc(2025);

        // Üye dekont yükler → inceleme durumuna geçer
        $this->actingAs($this->member)
            ->post(route('customer.dues.pay-transfer', $due), [
                'receipt' => \Illuminate\Http\UploadedFile::fake()->image('dekont.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $due->refresh();
        $this->assertSame('review', $due->status);
        $this->assertNotNull($due->receipt_path);

        // İkinci kez yükleyemez
        $this->actingAs($this->member)
            ->post(route('customer.dues.pay-transfer', $due), [
                'receipt' => \Illuminate\Http\UploadedFile::fake()->image('dekont2.jpg'),
            ]);
        $this->assertSame('review', $due->fresh()->status);

        // Başkasının borcuna dekont yükleyemez
        $baskasi = User::create([
            'name' => 'Diğer Üye', 'tc_no' => '10000000067',
            'password' => \Illuminate\Support\Facades\Hash::make('sifre123'), 'role' => 'customer',
            'customer_group_id' => $this->member->customer_group_id, 'is_active' => true,
        ]);
        $this->actingAs($baskasi)
            ->post(route('customer.dues.pay-transfer', $due), [
                'receipt' => \Illuminate\Http\UploadedFile::fake()->image('dekont3.jpg'),
            ])->assertForbidden();

        // Yönetici dekontu görüp onaylar; faiz kayda geçer
        $this->actingAs($this->admin)->get(route('documents.dues-receipt', $due))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.dues.paid', $due), ['method' => 'bank_transfer'])
            ->assertSessionHasNoErrors();

        $due->refresh();
        $this->assertSame('paid', $due->status);
        $this->assertSame('42.00', $due->late_fee);
    }
}
