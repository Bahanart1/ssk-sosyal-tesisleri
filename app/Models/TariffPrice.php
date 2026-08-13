<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TariffPrice extends Model
{
    protected $fillable = [
        'tariff_id', 'customer_group_id', 'adult_price', 'child_price', 'min_daily_total',
    ];

    protected function casts(): array
    {
        return [
            'adult_price' => 'decimal:2',
            'child_price' => 'decimal:2',
            'min_daily_total' => 'decimal:2',
        ];
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
