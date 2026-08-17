<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $fillable = [
        'facility_id', 'name', 'code', 'kind', 'bed_count', 'is_ground_floor',
        'min_billed_persons', 'max_persons', 'waive_empty_bed_at_occupancy',
        'quantity', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_ground_floor' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function isVilla(): bool
    {
        return $this->kind === 'villa';
    }

    /** Odada konaklayabilecek azami kişi sayısı (villalarda yataksız ilave kişi mümkün). */
    public function capacity(): int
    {
        return $this->max_persons ?: $this->bed_count;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('bed_count');
    }
}
