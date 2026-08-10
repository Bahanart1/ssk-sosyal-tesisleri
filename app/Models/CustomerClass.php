<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerClass extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'daily_price'];

    protected function casts(): array
    {
        return [
            'daily_price' => 'decimal:2',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
