<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * İki tesis ve oda tipleri.
 * "Çolaklı tesisinde bir, iki ve dört kişilik odalar ile villalar; Güre tesisinde ise
 *  üç ve dört kişilik odalar ... düzenlenerek Dernek tarafından ilan edilir." (Madde 4/3)
 */
class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $colakli = Facility::updateOrCreate(['slug' => 'colakli'], [
            'name' => 'Çolaklı Tatil Beldesi',
            'location' => 'Antalya / Manavgat',
            'description' => 'Akdeniz kıyısında, denize sıfır konumda; bir, iki ve dört kişilik odalar ile üç odalı villalar, havuz, plaj ve alakart restoran.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $gure = Facility::updateOrCreate(['slug' => 'gure'], [
            'name' => 'Güre Tatil Beldesi',
            'location' => 'Balıkesir / Edremit',
            'description' => 'Kaz Dağları eteklerinde, termal kaynaklarıyla bilinen Güre’de; üç ve dört kişilik odalar, havuz ve plaj.',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $roomTypes = [
            // Çolaklı
            ['facility' => $colakli, 'code' => 'colakli-1-kisilik', 'name' => '1 Kişilik Oda', 'kind' => 'room', 'bed_count' => 1, 'quantity' => 12, 'sort_order' => 1,
                'description' => 'Tek yataklı standart oda.'],
            ['facility' => $colakli, 'code' => 'colakli-2-kisilik', 'name' => '2 Kişilik Oda', 'kind' => 'room', 'bed_count' => 2, 'quantity' => 48, 'sort_order' => 2,
                'description' => 'İki yataklı standart oda.'],
            ['facility' => $colakli, 'code' => 'colakli-2-kisilik-zemin', 'name' => '2 Kişilik Oda (Zemin Kat)', 'kind' => 'room', 'bed_count' => 2, 'quantity' => 14, 'sort_order' => 3,
                'is_ground_floor' => true,
                'description' => 'Zemin katta iki kişilik oda. Ortopedik engel, yaşlılık veya sağlık mazereti olanlar için; kişi başı günlük ücrette %10 indirim uygulanır.'],
            ['facility' => $colakli, 'code' => 'colakli-4-kisilik', 'name' => '4 Kişilik Oda', 'kind' => 'room', 'bed_count' => 4, 'quantity' => 36, 'sort_order' => 4,
                'description' => 'Dört yataklı aile odası.'],
            ['facility' => $colakli, 'code' => 'colakli-villa', 'name' => 'Villa (3 Oda / 5 Yatak)', 'kind' => 'villa', 'bed_count' => 5, 'quantity' => 10, 'sort_order' => 5,
                'min_billed_persons' => 5, 'max_persons' => 6,
                'description' => 'Üç oda ve beş yataktan oluşan villa; yemeksiz konaklama. En az beş kişi üzerinden ücretlendirilir. Zorunluluk halinde ilave ücretle altıncı kişi konaklayabilir, ancak yatak sağlanmaz.'],

            // Güre
            ['facility' => $gure, 'code' => 'gure-3-kisilik', 'name' => '3 Kişilik Oda', 'kind' => 'room', 'bed_count' => 3, 'quantity' => 40, 'sort_order' => 1,
                'waive_empty_bed_at_occupancy' => 2,
                'description' => 'Üç yataklı oda. İki kişi konaklaması halinde kalan bir yatak için ücret alınmaz.'],
            ['facility' => $gure, 'code' => 'gure-4-kisilik', 'name' => '4 Kişilik Oda', 'kind' => 'room', 'bed_count' => 4, 'quantity' => 28, 'sort_order' => 2,
                'description' => 'Dört yataklı aile odası.'],
        ];

        foreach ($roomTypes as $rt) {
            $facility = $rt['facility'];
            unset($rt['facility']);

            RoomType::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $rt['code']],
                $rt + ['facility_id' => $facility->id, 'is_active' => true]
            );
        }
    }
}
