<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'user_id', 'facility_id', 'room_type_id', 'room_id',
        'period_id', 'second_period_id', 'start_date', 'end_date', 'nights',
        'status', 'ground_floor_request', 'ground_floor_note', 'health_report_path',
        'application_date', 'surcharge_per_person_day',
        'empty_bed_count', 'empty_bed_fee_per_day', 'empty_bed_total',
        'accommodation_total', 'adjustment_amount', 'adjustment_note', 'total_price',
        'deposit_amount', 'deposit_status', 'balance_due_date', 'price_breakdown',
        'note', 'admin_note', 'decided_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'application_date' => 'date',
            'balance_due_date' => 'date',
            'ground_floor_request' => 'boolean',
            'surcharge_per_person_day' => 'decimal:2',
            'empty_bed_fee_per_day' => 'decimal:2',
            'empty_bed_total' => 'decimal:2',
            'accommodation_total' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'price_breakdown' => 'array',
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

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    /** Atanan fiziksel oda (blok + numara). */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }

    public function secondPeriod()
    {
        return $this->belongsTo(Period::class, 'second_period_id');
    }

    public function guests()
    {
        return $this->hasMany(ReservationGuest::class)->orderBy('sort_order');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function depositPayment()
    {
        return $this->hasOne(Payment::class)->where('kind', 'deposit')->latestOfMany();
    }

    public function balancePayment()
    {
        return $this->hasOne(Payment::class)->where('kind', 'balance')->latestOfMany();
    }

    /** @return list<Period> Bir veya ardışık iki devre. */
    public function periodList(): array
    {
        return array_values(array_filter([$this->period, $this->secondPeriod]));
    }

    public function isTwoPeriods(): bool
    {
        return $this->second_period_id !== null;
    }

    public function depositVerified(): bool
    {
        return $this->deposit_status === 'verified';
    }

    /** Ödenmiş peşinat düşüldükten sonra kalan tutar (Madde 8/8). */
    public function balanceDue(): float
    {
        $paid = (float) $this->payments()
            ->where('status', 'success')
            ->sum('amount');

        return max(0, round((float) $this->total_price - $paid, 2));
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->where('status', 'success')->sum('amount');
    }

    /** Devre başlangıcına en az 10 gün varsa iptal edilebilir (Madde 8/11). */
    public function isCancellable(): bool
    {
        if (! in_array($this->status, ['pending', 'approved'], true)) {
            return false;
        }

        $minDays = (int) Setting::number('cancellation.min_days_before', 10);

        return now()->startOfDay()->diffInDays($this->start_date, false) >= $minDays;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'İnceleniyor',
            'approved' => 'Yer Tahsis Edildi · Ödeme Bekleniyor',
            'paid' => 'Ödendi',
            'rejected' => 'Reddedildi',
            'cancelled' => 'İptal Edildi',
            default => $this->status,
        };
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
