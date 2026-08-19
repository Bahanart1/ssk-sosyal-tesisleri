<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Petition extends Model
{
    use HasFactory;

    /** Dilekçe konuları — yöneticinin süzebilmesi için sabit liste. */
    public const CATEGORIES = [
        'reservation' => 'Rezervasyon talebi',
        'guest_change' => 'Kişi ekleme / çıkarma',
        'cancellation' => 'İptal ve iade',
        'dues' => 'Aidat',
        'complaint' => 'Şikâyet / öneri',
        'other' => 'Diğer',
    ];

    protected $fillable = [
        'user_id', 'reservation_id', 'subject', 'category', 'body',
        'attachment_path', 'status', 'reply', 'replied_by', 'replied_at',
    ];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Yanıt Bekliyor',
            'answered' => 'Yanıtlandı',
            'closed' => 'Kapatıldı',
            default => $this->status,
        };
    }
}
