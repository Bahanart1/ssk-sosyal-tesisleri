<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'tc_no', 'phone', 'password',
        'role', 'customer_class_id', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function customerClass()
    {
        return $this->belongsTo(CustomerClass::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * TC kimlik numarasını maskeler: 123**5**89
     */
    public function maskedTcNo(): string
    {
        if (! $this->tc_no) {
            return '-';
        }

        return substr($this->tc_no, 0, 3) . str_repeat('*', 5) . substr($this->tc_no, -3);
    }
}
