<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\MembershipDue;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $groups = CustomerGroup::pluck('id', 'code');
        $year = (int) now()->year;
        $duesAmount = Setting::number('dues.annual_amount', 200);

        $admin = User::updateOrCreate(['email' => 'admin@sigortader.com.tr'], [
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
                'address' => 'Çankaya / Ankara',
                'joined_at' => '2019-03-12',
                'customer_group_id' => $groups['I'],
                'duesPaidThrough' => $year,       // borcu yok
            ],
            [
                'name' => 'Ayşe Demir',
                'membership_no' => 'U-2087',
                'tc_no' => '98765432109',
                'phone' => '0555 987 65 43',
                'email' => 'ayse.demir@example.com',
                'address' => 'Kadıköy / İstanbul',
                'joined_at' => '2021-06-01',
                'customer_group_id' => $groups['II'],
                'duesPaidThrough' => $year,
            ],
            [
                // Aidat borcu olan üye — müracaat formu işleme alınmaz (Madde 5/10)
                'name' => 'Mehmet Kaya',
                'membership_no' => 'U-3311',
                'tc_no' => '11122233344',
                'phone' => '0555 111 22 33',
                'email' => 'mehmet.kaya@example.com',
                'address' => 'Konak / İzmir',
                'joined_at' => '2022-01-20',
                'customer_group_id' => $groups['I'],
                'duesPaidThrough' => $year - 2,   // son iki yıl borçlu
            ],
            [
                // Dernek üyesi olmayan misafir — aidat koşulundan muaf
                'name' => 'Zeynep Şahin',
                'tc_no' => '55566677788',
                'phone' => '0555 555 66 77',
                'email' => 'zeynep.sahin@example.com',
                'customer_group_id' => $groups['III'],
                'duesPaidThrough' => null,
            ],
        ];

        foreach ($customers as $attributes) {
            $paidThrough = $attributes['duesPaidThrough'];
            unset($attributes['duesPaidThrough']);

            $customer = User::updateOrCreate(['tc_no' => $attributes['tc_no']], $attributes + [
                'password' => Hash::make('musteri123'),
                'role' => 'customer',
                'is_active' => true,
            ]);

            if ($paidThrough === null) {
                continue;
            }

            // Üyelik yılından bu yana her yıl için tahakkuk; ödenen yıllar işaretlenir
            $from = $customer->joined_at ? (int) $customer->joined_at->year : $year;

            for ($y = max($from, $year - 3); $y <= $year; $y++) {
                $paid = $y <= $paidThrough;

                MembershipDue::updateOrCreate(
                    ['user_id' => $customer->id, 'year' => $y],
                    [
                        'amount' => $duesAmount,
                        'status' => $paid ? 'paid' : 'unpaid',
                        'paid_at' => $paid ? "{$y}-02-15" : null,
                        'method' => $paid ? 'bank_transfer' : null,
                        'recorded_by' => $admin->id,
                    ]
                );
            }
        }
    }
}
