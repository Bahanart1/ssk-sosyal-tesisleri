<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id', 'user_id', 'reason',
        'gross_amount', 'deduction', 'amount', 'status',
        'iban', 'account_holder', 'iban_submitted_at',
        'reference_no', 'paid_at', 'processed_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'deduction' => 'decimal:2',
            'amount' => 'decimal:2',
            'iban_submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeAwaitingIban($query)
    {
        return $query->where('status', 'awaiting_iban');
    }

    /** Ödenmeyi bekleyen iadeler — yöneticinin havale yapacağı liste. */
    public function scopePayable($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['awaiting_iban', 'pending']);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'awaiting_iban' => 'IBAN Bekleniyor',
            'pending' => 'Ödeme Bekleniyor',
            'paid' => 'İade Edildi',
            'cancelled' => 'İptal Edildi',
            default => $this->status,
        };
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'rejected' => 'Yer tahsis edilemedi',
            'cancelled' => 'Üye iptal etti',
            default => $this->reason,
        };
    }

    /** IBAN'ı okunur biçimde dörtlü gruplar hâlinde yazar. */
    public function ibanFormatted(): ?string
    {
        return $this->iban ? trim(chunk_split($this->iban, 4, ' ')) : null;
    }
}
