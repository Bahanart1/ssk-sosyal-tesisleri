<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'membership_no', 'tc_no', 'phone', 'password',
        'role', 'customer_group_id', 'dues_paid_year', 'is_active',
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

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
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
     * İçinde bulunulan yıl dahil aidat borcu bulunan üyelerin müracaat formları
     * işleme alınmaz (Madde 5/10). Dernek üyesi olmayanlar (III. Grup) muaftır.
     */
    public function hasDuesDebt(?int $year = null): bool
    {
        if (! $this->customerGroup?->requires_membership) {
            return false;
        }

        $year ??= (int) now()->year;

        return $this->dues_paid_year === null || $this->dues_paid_year < $year;
    }

    public function canApply(): bool
    {
        return $this->is_active
            && $this->customer_group_id !== null
            && ! $this->hasDuesDebt();
    }

    /** TC kimlik numarasını maskeler: 123*****789 */
    public function maskedTcNo(): string
    {
        if (! $this->tc_no) {
            return '-';
        }

        return substr($this->tc_no, 0, 3) . str_repeat('*', 5) . substr($this->tc_no, -3);
    }
}
