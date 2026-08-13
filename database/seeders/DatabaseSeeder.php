<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            CustomerGroupSeeder::class,
            FacilitySeeder::class,
            Camp2026Seeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
