<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bir odanın bir devrede hangi başvuruya ait olduğunu tutar.
 *
 * Satırlar ReservationObserver tarafından başvurudan türetilir; elle
 * oluşturulmaz. `(room_id, period_id)` tekil olduğu için çifte tahsis
 * veritabanı seviyesinde imkânsızdır.
 */
class RoomPeriodOccupancy extends Model
{
    protected $fillable = ['reservation_id', 'room_id', 'period_id'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }
}
