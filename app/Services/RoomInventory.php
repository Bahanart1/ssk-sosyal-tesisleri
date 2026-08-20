<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Room;
use App\Models\RoomType;

/**
 * Oda tipi adetlerini fiziksel oda envanterinden türetir.
 *
 * Bu kural daha önce hem ssk:import-rooms komutunda hem de oda envanteri
 * ekranında ayrı ayrı yazılmıştı ve kopyalar birbirinden ayrışmıştı: komut,
 * odası kalmayan tipi pasife alıyor, ekran almıyordu. Ekran üzerinden son odası
 * pasife alınan bir oda tipi, sıfır odayla rezervasyona açık kalıyordu.
 * Kural tek sahipte toplandı.
 */
class RoomInventory
{
    /**
     * Tesisin oda tiplerindeki adetleri yeniden hesaplar.
     *
     * Villalar fiziksel envantere dahil olmadığından dokunulmaz.
     *
     * @param  bool  $deactivateEmpty  Odası kalmayan tip rezervasyona kapatılsın mı
     * @return list<string> Pasife alınan oda tiplerinin adları
     */
    public function sync(Facility $facility, bool $deactivateEmpty = true): array
    {
        $counts = Room::where('facility_id', $facility->id)
            ->where('is_active', true)
            ->selectRaw('room_type_id, COUNT(*) as total')
            ->groupBy('room_type_id')
            ->pluck('total', 'room_type_id');

        $deactivated = [];

        $types = RoomType::where('facility_id', $facility->id)
            ->where('kind', 'room')
            ->get();

        foreach ($types as $type) {
            $total = (int) ($counts[$type->id] ?? 0);

            $type->quantity = $total;

            if ($total === 0 && $deactivateEmpty && $type->is_active) {
                $type->is_active = false;
                $deactivated[] = $type->name;
            }

            $type->save();
        }

        return $deactivated;
    }
}
