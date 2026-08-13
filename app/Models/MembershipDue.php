<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipDue extends Model
{
    protected $fillable = [
        'user_id', 'year', 'amount', 'status', 'paid_at',
        'method', 'receipt_no', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public const METHODS = [
        'cash' => 'Nakit',
        'bank_transfer' => 'Havale / EFT',
        'card' => 'Kredi / Banka Kartı',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Tahsil edilmiş sayılan durumlar: ödenmiş veya muaf tutulmuş. */
    public function isSettled(): bool
    {
        return in_array($this->status, ['paid', 'waived'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Ödendi',
            'waived' => 'Muaf',
            default => 'Borçlu',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'paid' => 'green',
            'waived' => 'gray',
            default => 'red',
        };
    }

    public function methodLabel(): ?string
    {
        return $this->method ? (self::METHODS[$this->method] ?? $this->method) : null;
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopeSettled($query)
    {
        return $query->whereIn('status', ['paid', 'waived']);
    }

    /** Vadesi gelmiş: içinde bulunulan yıl dahil. */
    public function scopeDue($query, ?int $year = null)
    {
        return $query->where('year', '<=', $year ?? now()->year);
    }
}
