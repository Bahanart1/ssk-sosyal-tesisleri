<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = [
        'facility_id', 'year', 'name', 'scope', 'is_discounted', 'empty_bed_fee', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_discounted' => 'boolean',
            'empty_bed_fee' => 'decimal:2',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function prices()
    {
        return $this->hasMany(TariffPrice::class);
    }

    public function priceFor(CustomerGroup|int $group): ?TariffPrice
    {
        $id = $group instanceof CustomerGroup ? $group->id : $group;

        return $this->prices->firstWhere('customer_group_id', $id);
    }

    /** İndirimli devrelerde boş yatak ücreti alınmaz (Madde 8/9). */
    public function emptyBedFee(): float
    {
        return $this->empty_bed_fee === null ? 0.0 : (float) $this->empty_bed_fee;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
