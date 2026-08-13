<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

/**
 * Ücret tablolarındaki üç müşteri grubu.
 */
class CustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'code' => 'I',
                'name' => 'I. Grup',
                'description' => 'Dernek Üyesi ve Bakmakla Yükümlü Olduğu Aile Fertleri (eşi, çocuğu, anne ve babası)',
                'requires_membership' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'II',
                'name' => 'II. Grup',
                'description' => 'Dernek Üyesinin Gelini, Damadı ve Torunu',
                'requires_membership' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'III',
                'name' => 'III. Grup',
                'description' => 'Dernek Üyesi Olmayanlar (Misafir)',
                'requires_membership' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($groups as $group) {
            CustomerGroup::updateOrCreate(['code' => $group['code']], $group);
        }
    }
}
