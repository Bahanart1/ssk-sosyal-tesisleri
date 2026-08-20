<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\MembershipDue;
use App\Models\Petition;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Yetkilendirme Policy sınıflarına taşındı; bu testler kuralın gerçekten
 * uygulandığını doğrular. Aksi halde policy'ler sessizce devre dışı kalabilir
 * ve her istek geçerdi.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $sahip;

    private User $yabanci;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class]);

        $grup = CustomerGroup::where('code', 'I')->value('id');

        $this->sahip = $this->uye('11111111111', $grup);
        $this->yabanci = $this->uye('22222222222', $grup);

        $this->admin = User::create([
            'name' => 'Yönetici',
            'email' => 'policy-admin@example.test',
            'password' => 'gizli-sifre',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_uye_baskasinin_aidatini_goremez(): void
    {
        $due = MembershipDue::create([
            'user_id' => $this->sahip->id,
            'year' => 2026,
            'amount' => 2500,
            'status' => 'unpaid',
        ]);

        $this->assertTrue(Gate::forUser($this->sahip)->allows('view', $due));
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $due));
        $this->assertFalse(Gate::forUser($this->yabanci)->allows('view', $due));

        $this->assertTrue(Gate::forUser($this->sahip)->allows('act', $due));
        $this->assertFalse(Gate::forUser($this->yabanci)->allows('act', $due));
    }

    public function test_uye_baskasinin_dilekcesini_goremez(): void
    {
        $petition = Petition::create([
            'user_id' => $this->sahip->id,
            'subject' => 'Deneme',
            'body' => 'Deneme dilekçesi',
            'status' => 'open',
        ]);

        $this->assertTrue(Gate::forUser($this->sahip)->allows('view', $petition));
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $petition));
        $this->assertFalse(Gate::forUser($this->yabanci)->allows('view', $petition));
    }

    public function test_policy_sinifi_bulunamazsa_test_de_dusmeli(): void
    {
        // Policy otomatik keşfinin çalıştığını doğrular: sınıf bulunamazsa
        // Gate varsayılan olarak reddeder ve sahibi de geçemezdi.
        $this->assertNotNull(Gate::getPolicyFor(MembershipDue::class));
        $this->assertNotNull(Gate::getPolicyFor(Petition::class));
        $this->assertNotNull(Gate::getPolicyFor(Reservation::class));
        $this->assertNotNull(Gate::getPolicyFor(Refund::class));
    }

    /**
     * Super admin Gate::before ile bütün yetkileri taşır — Spatie'nin önerdiği
     * kalıp. Üye paneline erişimi bu yüzden açılmaz: o kapı yetkiyle değil,
     * hesap türüyle (role:customer middleware) korunur.
     */
    public function test_yonetici_uye_paneline_giremez(): void
    {
        $this->actingAs($this->admin)
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('login'));
    }

    /** Üye de yönetim paneline giremez. */
    public function test_uye_yonetim_paneline_giremez(): void
    {
        $this->actingAs($this->sahip)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    private function uye(string $tc, int $grup): User
    {
        return User::create([
            'name' => 'Üye '.$tc,
            'tc_no' => $tc,
            'password' => 'gizli-sifre',
            'role' => 'customer',
            'customer_group_id' => $grup,
            'is_active' => true,
        ]);
    }
}
