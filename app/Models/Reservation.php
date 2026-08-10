<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'facility_id', 'customer_class_id',
        'check_in', 'check_out', 'guests', 'note',
        'total_price', 'status', 'admin_note', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_price' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function customerClass()
    {
        return $this->belongsTo(CustomerClass::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function nights(): int
    {
        return max(1, $this->check_in->diffInDays($this->check_out));
    }

    /** Haftalık kamp süresi (gece sayısı). */
    public const CAMP_NIGHTS = 7;

    /**
     * Verilen Pazartesi başlangıçlı kamp haftasının çıkış günü (sonraki Pazartesi).
     */
    public static function campCheckOut(\Carbon\CarbonInterface $checkIn): \Carbon\CarbonInterface
    {
        return $checkIn->copy()->startOfDay()->addDays(self::CAMP_NIGHTS);
    }

    public static function isValidCampWeek(\Carbon\CarbonInterface $checkIn, \Carbon\CarbonInterface $checkOut): bool
    {
        $checkIn = $checkIn->copy()->startOfDay();
        $checkOut = $checkOut->copy()->startOfDay();

        return $checkIn->isMonday()
            && $checkOut->equalTo(self::campCheckOut($checkIn));
    }

    public static function calculatePrice(CustomerClass $class, int $nights): float
    {
        return round((float) $class->daily_price * $nights, 2);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Onay Bekliyor',
            'approved' => 'Onaylandı',
            'rejected' => 'Reddedildi',
            'paid' => 'Ödendi',
            'cancelled' => 'İptal Edildi',
            default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending' => 'amber',
            'approved' => 'teal',
            'rejected' => 'red',
            'paid' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
