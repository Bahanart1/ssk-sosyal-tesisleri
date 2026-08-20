<?php

namespace App\Models;

use App\Observers\ReservationObserver;
use App\Support\ReservationStatus;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(ReservationObserver::class)]
class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'user_id', 'facility_id', 'room_type_id', 'top_customer_group_id', 'room_id', 'second_room_id',
        'period_id', 'second_period_id', 'start_date', 'end_date', 'nights',
        'status', 'ground_floor_request', 'ground_floor_note', 'health_report_path',
        'application_date', 'surcharge_per_person_day',
        'empty_bed_count', 'empty_bed_fee_per_day', 'empty_bed_total',
        'accommodation_total', 'adjustment_amount', 'adjustment_note', 'total_price',
        'deposit_amount', 'deposit_status', 'collect_on_site_at', 'balance_due_date', 'price_breakdown',
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
            'collect_on_site_at' => 'datetime',
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

    /** Başvurudaki en yüksek müşteri grubu (I > II > III). */
    public function topCustomerGroup()
    {
        return $this->belongsTo(CustomerGroup::class, 'top_customer_group_id');
    }

    /** Atanan fiziksel oda (blok + numara). */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /** Kalabalık aileler için yöneticinin verdiği ikinci oda. */
    public function secondRoom()
    {
        return $this->belongsTo(Room::class, 'second_room_id');
    }

    /** @return list<Room> Tahsis edilmiş odalar. */
    public function roomList(): array
    {
        return array_values(array_filter([$this->room, $this->secondRoom]));
    }

    /** Odaların birlikte sunduğu yatak kapasitesi. */
    public function allocatedCapacity(): int
    {
        return $this->second_room_id ? $this->roomType->capacity() * 2 : $this->roomType->capacity();
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

    /** Karara bağlanan başvurunun iade kaydı (varsa). */
    public function refund()
    {
        return $this->hasOne(Refund::class);
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

    /** Bakiyesi tesiste tahsil edilecek, kesinleşmiş rezervasyon. */
    public function collectsOnSite(): bool
    {
        return $this->collect_on_site_at !== null && $this->status !== ReservationStatus::CANCELLED;
    }

    /** Tesiste tahsil edilecek tutar. */
    public function onSiteAmount(): float
    {
        return $this->collectsOnSite() ? $this->balanceDue() : 0.0;
    }

    /** Tesiste tahsil edilmiş tutar. */
    public function onSiteCollected(): float
    {
        return (float) $this->payments()
            ->where('method', 'on_site')
            ->where('status', 'success')
            ->sum('amount');
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

    /**
     * Devre başlayana kadar iptal edilebilir. Son günlerdeki iptal engellenmez;
     * bunun yerine kesinti uygulanır (son 10 günde konaklamanın üçte biri).
     */
    public function isCancellable(): bool
    {
        if (! in_array($this->status, [ReservationStatus::PENDING, ReservationStatus::APPROVED], true)) {
            return false;
        }

        return now()->startOfDay()->lte($this->start_date);
    }

    /** Devre başlangıcına 10 günden az kaldı: iptalde oransal kesinti uygulanır. */
    public function isLateCancel(): bool
    {
        $minDays = (int) Setting::number('cancellation.min_days_before', 10);

        return now()->startOfDay()->diffInDays($this->start_date, false) < $minDays;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            ReservationStatus::PENDING => 'İnceleniyor',
            ReservationStatus::APPROVED => $this->collect_on_site_at
                ? 'Tesiste Ödeyecek'
                : 'Yer Tahsis Edildi · Ödeme Bekleniyor',
            ReservationStatus::PAID => 'Ödendi',
            ReservationStatus::REJECTED => 'Reddedildi',
            ReservationStatus::CANCELLED => 'İptal Edildi',
            default => $this->status,
        };
    }

    public function occupancies()
    {
        return $this->hasMany(RoomPeriodOccupancy::class);
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
