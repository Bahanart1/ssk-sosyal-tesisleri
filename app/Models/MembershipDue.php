<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipDue extends Model
{
    protected $fillable = [
        'user_id', 'year', 'amount', 'late_fee', 'status', 'paid_at',
        'method', 'receipt_no', 'receipt_path', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'late_fee' => 'decimal:2',
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

    /**
     * Gecikilen tam ay sayısı. Yılın aidatı o yılın sonuna kadar ödenir;
     * faiz, izleyen yılın 1 Ocak'ından itibaren ay başına işler. İçinde
     * bulunulan yılın aidatına faiz uygulanmaz.
     */
    public function lateMonths(): int
    {
        if ($this->isSettled() || $this->year >= now()->year) {
            return 0;
        }

        $vade = now()->create($this->year + 1, 1, 1)->startOfDay();

        return max(0, (int) $vade->diffInMonths(now()->startOfDay()));
    }

    /**
     * Gecikme faizi. Ödenmişlerde tahsilat anında yazılan tutar okunur;
     * borçlularda ayarlardaki aylık orana göre anlık hesaplanır (basit faiz).
     */
    public function interestAmount(): float
    {
        if ($this->isSettled()) {
            return (float) $this->late_fee;
        }

        $aylikOran = (float) Setting::number('dues.late_fee_monthly_percent', 0);

        if ($aylikOran <= 0) {
            return 0.0;
        }

        return round((float) $this->amount * ($aylikOran / 100) * $this->lateMonths(), 2);
    }

    /** Anapara + gecikme faizi. */
    public function totalDue(): float
    {
        return round((float) $this->amount + $this->interestAmount(), 2);
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
            'review' => 'Dekont İnceleniyor',
            default => 'Borçlu',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'paid' => 'green',
            'waived' => 'gray',
            'review' => 'amber',
            default => 'red',
        };
    }

    public function methodLabel(): ?string
    {
        return $this->method ? (self::METHODS[$this->method] ?? $this->method) : null;
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['unpaid', 'review']);
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
