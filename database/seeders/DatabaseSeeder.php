<?php

namespace Database\Seeders;

use App\Models\CustomerClass;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Müşteri sınıfları ve günlük fiyatlar
        $class1 = CustomerClass::create(['name' => 'Class 1', 'description' => 'SSK Emeklisi', 'daily_price' => 350]);
        $class2 = CustomerClass::create(['name' => 'Class 2', 'description' => 'Aktif Sigortalı', 'daily_price' => 500]);
        $class3 = CustomerClass::create(['name' => 'Class 3', 'description' => 'Misafir / Diğer', 'daily_price' => 750]);

        // Tesisler
        $facilities = [
            ['name' => 'Kemer Deniz Manzaralı Tesis', 'location' => 'Antalya / Kemer', 'capacity' => 4, 'description' => 'Denize sıfır, aile konseptli konaklama tesisi.'],
            ['name' => 'Kızılcahamam Termal Tesis', 'location' => 'Ankara / Kızılcahamam', 'capacity' => 3, 'description' => 'Doğa içinde termal kaynaklı dinlenme tesisi.'],
            ['name' => 'Ayvalık Sahil Bungalov', 'location' => 'Balıkesir / Ayvalık', 'capacity' => 2, 'description' => 'Sakin koylarda özel bungalov konaklama.'],
            ['name' => 'Uludağ Kayak Merkezi Tesisi', 'location' => 'Bursa / Uludağ', 'capacity' => 5, 'description' => 'Kış sporları meraklıları için dağ tesisi.'],
        ];
        foreach ($facilities as $f) {
            Facility::create($f);
        }

        // Admin kullanıcı
        User::create([
            'name' => 'Sistem Yöneticisi',
            'email' => 'admin@ssk-tesisleri.gov.tr',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        // Demo müşteriler
        User::create([
            'name' => 'Ahmet Yılmaz',
            'tc_no' => '12345678901',
            'phone' => '05551234567',
            'password' => Hash::make('musteri123'),
            'role' => 'customer',
            'customer_class_id' => $class1->id,
        ]);

        User::create([
            'name' => 'Ayşe Demir',
            'tc_no' => '98765432109',
            'phone' => '05559876543',
            'password' => Hash::make('musteri123'),
            'role' => 'customer',
            'customer_class_id' => $class2->id,
        ]);
    }
}
