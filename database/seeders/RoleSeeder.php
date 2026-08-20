<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Panel rolleri.
 *
 * super-admin  → Gate::before ile bütün yetkileri taşır; listesi tutulmaz.
 * calisan      → günlük iş yetkileri; super admin ekrandan değiştirebilir.
 *
 * Yeniden çalıştırıldığında mevcut rolün yetkileri **korunur**: seeder,
 * yöneticinin ekrandan yaptığı ayarları geri almaz. Yalnızca eksik yetki
 * tanımlarını ve rolleri oluşturur.
 */
class RoleSeeder extends Seeder
{
    public const SUPER_ADMIN = 'super-admin';

    public const STAFF = 'calisan';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(self::SUPER_ADMIN, 'web');

        $staff = Role::findOrCreate(self::STAFF, 'web');

        // Yalnızca ilk kurulumda doldur; sonraki çalıştırmalar ekrandan yapılan
        // ayarı ezmesin.
        if ($staff->permissions()->count() === 0) {
            $staff->syncPermissions(Permissions::defaultsForStaff());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
