<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Panel rolleri: super admin her şeyi yapar, çalışan yalnızca günlük işi.
 *
 * Hesap türü (users.role) ile yetki (Spatie) iki ayrı katmandır: çalışan da
 * yöneticidir, panele girer; ne yapabileceğini yetkileri belirler.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $calisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class]);

        $this->superAdmin = $this->yonetici('super@example.test');
        $this->calisan = $this->yonetici('calisan@example.test');
        $this->calisan->syncRoles([RoleSeeder::STAFF]);
    }

    /** Çalışan günlük iş ekranlarını açabilir. */
    public function test_calisan_gunluk_is_ekranlarini_acabilir(): void
    {
        foreach (['admin.reservations.index', 'admin.payments.index', 'admin.dues.index',
            'admin.petitions.index', 'admin.rooms.index', 'admin.customers.index',
            'admin.refunds.index', 'admin.on-site.index'] as $rota) {
            $this->actingAs($this->calisan)
                ->get(route($rota))
                ->assertOk("Çalışan {$rota} ekranını açabilmeli");
        }
    }

    /** Tanımlar ve parametreler çalışana kapalı. */
    public function test_calisan_tanim_ve_parametre_ekranlarina_giremez(): void
    {
        foreach (['admin.tariffs.index', 'admin.facilities.index', 'admin.settings.index', 'admin.staff.index'] as $rota) {
            $this->actingAs($this->calisan)
                ->get(route($rota))
                ->assertForbidden("Çalışan {$rota} ekranına girememeli");
        }
    }

    /** Geri dönüşü zor para işlemleri varsayılan olarak çalışanda değil. */
    public function test_calisan_riskli_para_islemlerini_yapamaz(): void
    {
        $this->assertFalse($this->calisan->can(Permissions::IADE_ODE));
        $this->assertFalse($this->calisan->can(Permissions::AIDAT_TAHAKKUK));
        $this->assertFalse($this->calisan->can(Permissions::AIDAT_SIL));
        $this->assertFalse($this->calisan->can(Permissions::BASVURU_IPTAL));

        // Toplu tahakkuk uç noktası da kapalı olmalı.
        $this->actingAs($this->calisan)
            ->post(route('admin.dues.accrue'), ['year' => 2026, 'amount' => 2500])
            ->assertForbidden();
    }

    /** Super admin hiçbir yetkiyi ayrıca almadan her şeyi yapar. */
    public function test_super_admin_her_yetkiye_sahiptir(): void
    {
        $this->assertSame(0, $this->superAdmin->getAllPermissions()->count(), 'Doğrudan atanmış yetkisi yok');

        foreach (Permissions::all() as $yetki) {
            $this->assertTrue($this->superAdmin->can($yetki), "Super admin {$yetki} yetkisini taşımalı");
        }
    }

    /** Yetkiler kod değişmeden ekrandan açılıp kapatılabilir. */
    public function test_calisan_yetkisi_sonradan_verilebilir(): void
    {
        $this->assertFalse($this->calisan->can(Permissions::IADE_ODE));

        $rol = Role::findByName(RoleSeeder::STAFF);
        $rol->givePermissionTo(Permissions::IADE_ODE);

        $this->assertTrue($this->calisan->fresh()->can(Permissions::IADE_ODE));
    }

    /** Rolü olmayan yönetici hiçbir şey yapamaz — güvenli varsayılan. */
    public function test_rolsuz_yonetici_yetkisizdir(): void
    {
        $rolsuz = $this->yonetici('rolsuz@example.test');
        $rolsuz->syncRoles([]);

        $this->actingAs($rolsuz)
            ->get(route('admin.reservations.index'))
            ->assertForbidden();
    }

    /** Menüde yalnızca yetkisi olan ekranlar görünür. */
    public function test_menude_yetkisiz_ekran_gorunmez(): void
    {
        $yanit = $this->actingAs($this->calisan)->get(route('admin.dashboard'))->assertOk();

        $yanit->assertSee('Başvurular')
            ->assertDontSee('Tarifeler')
            ->assertDontSee('Parametreler')
            ->assertDontSee('Yöneticiler');

        $this->actingAs($this->superAdmin)->get(route('admin.dashboard'))
            ->assertSee('Tarifeler')
            ->assertSee('Parametreler');
    }

    /** Son super admin kilitlenmeye karşı korunur. */
    public function test_son_super_admin_rolunu_birakamaz(): void
    {
        // superAdmin tek super admin; çalışana düşürülmeye çalışılıyor.
        $this->actingAs($this->superAdmin)
            ->put(route('admin.staff.update', $this->superAdmin), [
                'role' => RoleSeeder::STAFF,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($this->superAdmin->fresh()->hasRole(RoleSeeder::SUPER_ADMIN));
    }

    /** İkinci super admin varken rol değişikliğine izin verilir. */
    public function test_ikinci_super_admin_varken_rol_dusurulebilir(): void
    {
        $this->yonetici('ikinci@example.test')->syncRoles([RoleSeeder::SUPER_ADMIN]);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.staff.update', $this->superAdmin), [
                'role' => RoleSeeder::STAFF,
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->superAdmin->fresh()->hasRole(RoleSeeder::STAFF));
    }

    private function yonetici(string $email): User
    {
        return User::create([
            'name' => 'Yönetici '.$email,
            'email' => $email,
            'password' => 'gizli-sifre',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
