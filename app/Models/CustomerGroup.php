<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ücret tablolarındaki I. / II. / III. Grup.
 */
class CustomerGroup extends Model
{
    protected $fillable = ['code', 'name', 'description', 'requires_membership', 'sort_order'];

    protected function casts(): array
    {
        return ['requires_membership' => 'boolean'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function tariffPrices()
    {
        return $this->hasMany(TariffPrice::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
