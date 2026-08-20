<?php

namespace App\Models;

use App\Support\SearchText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'email', 'membership_no', 'tc_no', 'phone', 'birth_date', 'password', 'must_change_password', 'password_changed_at',
        'role', 'customer_group_id', 'joined_at', 'address', 'city', 'institution', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joined_at' => 'date',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /** Ad, TC ve üyelik numarası aranabilir metne katlanır. */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $user->search_index = SearchText::index(
                (string) $user->name,
                (string) $user->tc_no,
                (string) $user->membership_no,
            );
        });
    }

    public function petitions()
    {
        return $this->hasMany(Petition::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function dues()
    {
        return $this->hasMany(MembershipDue::class)->orderByDesc('year');
    }

    /**
     * Hesap türü: panel ayrımını bu belirler (role sütunu).
     *
     * Yöneticinin panelde *ne yapabileceği* Spatie rolleri ve yetkileriyle
     * belirlenir; bu iki katman bilinçli olarak ayrıdır.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /** Dernek üyeliği gerektiren bir gruba mı bağlı (I. ve II. Grup)? */
    public function isMember(): bool
    {
        return (bool) $this->customerGroup?->requires_membership;
    }

    /**
     * İçinde bulunulan yıl dahil önceki yıllara ait aidat borcu bulunan üyelerin
     * müracaat formları işleme alınmaz (Madde 5/10).
     *
     * Dernek üyesi olmayanlar (III. Grup) bu koşuldan muaftır.
     */
    public function hasDuesDebt(?int $year = null): bool
    {
        if (! $this->isMember()) {
            return false;
        }

        return $this->dues()
            ->unpaid()
            ->due($year)
            ->exists();
    }

    /** @return Collection<int, MembershipDue> Vadesi gelmiş ödenmemiş aidatlar. */
    public function outstandingDues(?int $year = null): Collection
    {
        if (! $this->isMember()) {
            return collect();
        }

        return $this->dues()->unpaid()->due($year)->orderBy('year')->get();
    }

    /** Anapara + gecikme faizi dahil toplam aidat borcu. */
    public function duesDebtTotal(?int $year = null): float
    {
        return round((float) $this->outstandingDues($year)->sum(fn ($d) => $d->totalDue()), 2);
    }

    /** Borcun yalnızca gecikme faizi kısmı. */
    public function duesInterestTotal(?int $year = null): float
    {
        return round((float) $this->outstandingDues($year)->sum(fn ($d) => $d->interestAmount()), 2);
    }

    /** Aidatın ödendiği son yıl — özet gösterimlerde kullanılır. */
    public function duesPaidThrough(): ?int
    {
        return $this->dues()->settled()->max('year');
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

        return substr($this->tc_no, 0, 3).str_repeat('*', 5).substr($this->tc_no, -3);
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }
}
