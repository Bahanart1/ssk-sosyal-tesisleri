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
