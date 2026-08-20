<?php

namespace App\Models;

use App\Support\ReservationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Blok ve numarasıyla tanımlı tek bir fiziksel oda.
 */
class Room extends Model
{
    protected $fillable = [
        'facility_id', 'room_type_id', 'block', 'number', 'is_active', 'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function occupancies()
    {
        return $this->hasMany(RoomPeriodOccupancy::class);
    }

    /** Bu odayı ikinci oda olarak kullanan başvurular. */
    public function secondaryReservations()
    {
        return $this->hasMany(Reservation::class, 'second_room_id');
    }

    /** @deprecated ReservationStatus::OCCUPYING kullanın; geriye dönük uyum için duruyor. */
    public const OCCUPYING_STATUSES = ReservationStatus::OCCUPYING;

    /**
     * Verilen devrelerde başka bir başvuruya atanmamış odalar.
     *
     * Doluluk room_period_occupancies tablosundan okunur; orada bir satır varsa
     * oda o devrede doludur. Birleşik devre ve ikinci oda halleri satırlara
     * zaten yansıdığı için burada ayrıca ele alınmaz.
     *
     * @param  list<int>  $periodIds
     * @param  int|null  $exceptReservationId  Düzenlenen başvurunun kendi odası hariç tutulur
     */
    public function scopeFreeForPeriods($query, array $periodIds, ?int $exceptReservationId = null)
    {
        return $query->whereDoesntHave('occupancies', function ($q) use ($periodIds, $exceptReservationId) {
            $q->whereIn('period_id', $periodIds);

            if ($exceptReservationId) {
                $q->where('reservation_id', '!=', $exceptReservationId);
            }
        });
    }

    /** "MENEKŞE 12" */
    public function label(): string
    {
        return "{$this->block} {$this->number}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Blok adına, ardından oda numarasına göre sayısal sıralama. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('block')->orderByRaw('CAST(number AS INTEGER)');
    }
}
