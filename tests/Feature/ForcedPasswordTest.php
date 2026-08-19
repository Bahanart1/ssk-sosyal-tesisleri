<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\User;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Şifresi TC olan üye, şifresini değiştirmeden panele giremez. */
class ForcedPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const TC = '99999999991';

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class]);

        $this->member = User::create([
            'name' => 'Kütük Üyesi', 'tc_no' => self::TC,
            'password' => Hash::make(self::TC), 'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'), 'is_active' => true,
        ]);
    }

    public function test_tc_ile_giren_uye_sifre_ekranina_yonlendirilir_ve_panel_kilitlenir(): void
    {
        $this->post('/giris', ['tc_no' => self::TC, 'password' => self::TC])
            ->assertRedirect(route('customer.password.force'));

        $this->assertTrue($this->member->fresh()->must_change_password);

        // Panele kaçış denemesi şifre ekranına döner
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.password.force'));
        $this->get(route('customer.reservations.index'))->assertRedirect(route('customer.password.force'));
        $this->get(route('customer.password.force'))->assertOk();
    }

    public function test_yeni_sifre_tc_olamaz_ve_degisince_panel_acilir(): void
    {
        $this->post('/giris', ['tc_no' => self::TC, 'password' => self::TC]);

        // TC'nin kendisi reddedilir
        $this->post(route('customer.password.force.update'), [
            'password' => self::TC, 'password_confirmation' => self::TC,
        ])->assertSessionHasErrors('password');

        // Geçerli şifre kabul edilir
        $this->post(route('customer.password.force.update'), [
            'password' => 'guclu-sifre-123', 'password_confirmation' => 'guclu-sifre-123',
        ])->assertRedirect(route('customer.dashboard'));

        $uye = $this->member->fresh();
        $this->assertFalse($uye->must_change_password);
        $this->assertNotNull($uye->password_changed_at);
        $this->assertTrue(Hash::check('guclu-sifre-123', $uye->password));

        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_kendi_sifresi_olan_uye_engellenmez(): void
    {
        $normal = User::create([
            'name' => 'Normal Üye', 'tc_no' => '99999999992',
            'password' => Hash::make('kendi-sifresi-1'), 'role' => 'customer',
            'customer_group_id' => $this->member->customer_group_id, 'is_active' => true,
        ]);

        $this->post('/giris', ['tc_no' => $normal->tc_no, 'password' => 'kendi-sifresi-1'])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertFalse($normal->fresh()->must_change_password);
    }
}
