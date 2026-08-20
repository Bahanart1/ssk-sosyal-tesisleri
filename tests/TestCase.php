<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Veritabanı kurulmayan testler (ör. basit sayfa testleri) de bu sınıfı
        // kullanır; rol tabloları yoksa sessizce atlanır.
        if (Schema::hasTable('permissions')) {
            $this->seed(RoleSeeder::class);
        }

        // Testlerde oluşturulan yönetici, aksi belirtilmedikçe super admin sayılır.
        // Böylece her test dosyasına rol ataması serpiştirmek gerekmez; bir
        // çalışanın kısıtlarını sınayan test rolü açıkça değiştirir.
        User::created(function (User $user) {
            if ($user->role === 'admin' && $user->roles()->count() === 0) {
                $user->assignRole(RoleSeeder::SUPER_ADMIN);
            }
        });
    }
}
