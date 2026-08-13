<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $groups = CustomerGroup::pluck('id', 'code');
        $year = (int) now()->year;

        User::updateOrCreate(['email' => 'admin@sigortader.com.tr'], [
            'name' => 'Sistem Yöneticisi',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $customers = [
            [
                'name' => 'Ahmet Yılmaz',
                'membership_no' => 'U-1042',
                'tc_no' => '12345678901',
                'phone' => '0555 123 45 67',
                'email' => 'ahmet.yilmaz@example.com',
                'customer_group_id' => $groups['I'],
                'dues_paid_year' => $year,
            ],
            [
                'name' => 'Ayşe Demir',
                'membership_no' => 'U-2087',
                'tc_no' => '98765432109',
                'phone' => '0555 987 65 43',
                'email' => 'ayse.demir@example.com',
                'customer_group_id' => $groups['II'],
                'dues_paid_year' => $year,
            ],
            [
                // Aidat borcu olan üye — müracaat formu işleme alınmaz (Madde 5/10)
                'name' => 'Mehmet Kaya',
                'membership_no' => 'U-3311',
                'tc_no' => '11122233344',
                'phone' => '0555 111 22 33',
                'email' => 'mehmet.kaya@example.com',
                'customer_group_id' => $groups['I'],
                'dues_paid_year' => $year - 2,
            ],
            [
                // Dernek üyesi olmayan misafir — aidat koşulundan muaf
                'name' => 'Zeynep Şahin',
                'tc_no' => '55566677788',
                'phone' => '0555 555 66 77',
                'email' => 'zeynep.sahin@example.com',
                'customer_group_id' => $groups['III'],
                'dues_paid_year' => null,
            ],
        ];

        foreach ($customers as $customer) {
            User::updateOrCreate(['tc_no' => $customer['tc_no']], $customer + [
                'password' => Hash::make('musteri123'),
                'role' => 'customer',
                'is_active' => true,
            ]);
        }
    }
}
