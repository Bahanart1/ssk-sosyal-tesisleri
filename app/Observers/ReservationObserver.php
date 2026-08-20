<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Models\RoomPeriodOccupancy;
use App\Support\ReservationStatus;
use Illuminate\Support\Facades\DB;

/**
 * Oda doluluğu satırlarını başvurudan türetir.
 *
 * Senkron tek yerde durur: oda ataması, devre değişikliği, iptal ya da onay —
 * hangi yoldan olursa olsun başvuru kaydedildiğinde doluluk yeniden yazılır.
 * Böylece "bir mutasyon noktasını atlamak" diye bir hata sınıfı kalmaz.
 */
class ReservationObserver
{
    public function saved(Reservation $reservation): void
    {
        $this->sync($reservation);
    }

    public function deleted(Reservation $reservation): void
    {
        RoomPeriodOccupancy::where('reservation_id', $reservation->id)->delete();
    }

    private function sync(Reservation $reservation): void
    {
        $istenen = $this->desiredRows($reservation);

        DB::transaction(function () use ($reservation, $istenen) {
            $mevcut = RoomPeriodOccupancy::where('reservation_id', $reservation->id)
                ->get()
                ->keyBy(fn (RoomPeriodOccupancy $o) => $o->room_id.':'.$o->period_id);

            foreach ($mevcut as $anahtar => $satir) {
                if (! isset($istenen[$anahtar])) {
                    $satir->delete();
                }
            }

            foreach ($istenen as $anahtar => $veri) {
                if (! $mevcut->has($anahtar)) {
                    RoomPeriodOccupancy::create($veri + ['reservation_id' => $reservation->id]);
                }
            }
        });
    }

    /**
     * Başvurunun işgal ettiği (oda, devre) çiftleri.
     *
     * Birleşik devre başvurusu her iki devreyi, ikinci oda tahsis edilmişse her
     * iki odayı işgal eder. Sonuçlanmış başvurular odayı serbest bırakır.
     *
     * @return array<string, array{room_id: int, period_id: int}>
     */
    private function desiredRows(Reservation $reservation): array
    {
        if (! in_array($reservation->status, ReservationStatus::OCCUPYING, true)) {
            return [];
        }

        $odalar = array_filter([$reservation->room_id, $reservation->second_room_id]);
        $devreler = array_filter([$reservation->period_id, $reservation->second_period_id]);

        $satirlar = [];

        foreach ($odalar as $oda) {
            foreach ($devreler as $devre) {
                $satirlar[$oda.':'.$devre] = ['room_id' => $oda, 'period_id' => $devre];
            }
        }

        return $satirlar;
    }
}
