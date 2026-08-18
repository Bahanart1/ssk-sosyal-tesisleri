<?php

namespace App\Models;

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

    /** Odayı fiilen işgal eden başvuru durumları. */
    public const OCCUPYING_STATUSES = ['pending', 'approved', 'paid'];

    /**
     * Verilen devrelerde başka bir başvuruya atanmamış odalar.
     *
     * Bir oda yalnızca başvurunun devresi boyunca doludur; aynı oda başka bir
     * devrede başkasına verilebilir. Birleşik devre başvuruları her iki devreyi
     * de işgal eder.
     *
     * @param  list<int>  $periodIds
     * @param  int|null  $exceptReservationId  Düzenlenen başvurunun kendi odası hariç tutulmaz
     */
    public function scopeFreeForPeriods($query, array $periodIds, ?int $exceptReservationId = null)
    {
        return $query->whereDoesntHave('reservations', function ($q) use ($periodIds, $exceptReservationId) {
            $q->whereIn('status', self::OCCUPYING_STATUSES)
                ->where(fn ($w) => $w->whereIn('period_id', $periodIds)->orWhereIn('second_period_id', $periodIds));

            if ($exceptReservationId) {
                $q->where('id', '!=', $exceptReservationId);
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
