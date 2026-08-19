<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id', 'kind', 'method', 'amount', 'installment', 'status',
        'reference_no', 'receipt_path', 'gateway', 'gateway_ref', 'gateway_payload',
        'failure_reason', 'verified_by', 'verified_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public static function newReference(): string
    {
        return 'SSK-' . strtoupper(Str::random(10));
    }

    public function kindLabel(): string
    {
        return $this->kind === 'deposit' ? 'Peşinat' : 'Bakiye';
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'card' => 'Kredi/Banka Kartı',
            'on_site' => 'Tesiste Ödeme',
            default => 'Havale / EFT',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => match ($this->method) {
                'bank_transfer' => 'Dekont İnceleniyor',
                'on_site' => 'Tesiste Tahsil Edilecek',
                default => 'Ödeme Bekleniyor',
            },
            'success' => 'Onaylandı',
            'failed' => 'Başarısız',
            'refunded' => 'İade Edildi',
            default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending' => 'amber',
            'success' => 'green',
            'failed' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }
}
