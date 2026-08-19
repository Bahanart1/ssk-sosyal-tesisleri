<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Devre — Pazar girişle başlar, takip eden Cumartesi sona erer (Madde 7/1).
 */
class Period extends Model
{
    protected $fillable = [
        'facility_id', 'year', 'number', 'start_date', 'end_date', 'nights',
        'is_discounted', 'combine_group', 'combines_with_id', 'room_tariff_id', 'villa_tariff_id', 'is_open', 'note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_discounted' => 'boolean',
            'is_open' => 'boolean',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function roomTariff()
    {
        return $this->belongsTo(Tariff::class, 'room_tariff_id');
    }

    public function villaTariff()
    {
        return $this->belongsTo(Tariff::class, 'villa_tariff_id');
    }

    /** Oda tipine göre geçerli tarife: villalar Tablo 2'den, odalar Tablo 1'den ücretlendirilir. */
    public function tariffFor(RoomType $roomType): ?Tariff
    {
        return $roomType->isVilla()
            ? ($this->villaTariff ?? $this->roomTariff)
            : $this->roomTariff;
    }

    public function label(): string
    {
        return $this->number . '. Devre';
    }

    public function dateRange(): string
    {
        return $this->start_date->translatedFormat('d F Y') . ' – ' . $this->end_date->translatedFormat('d F Y');
    }

    public function isPast(): bool
    {
        return $this->start_date->isBefore(now()->startOfDay());
    }

    /** Bu devreyle birleşebilen devre; yönetici Devre Ayarları'ndan belirler. */
    public function combinesWith()
    {
        return $this->belongsTo(Period::class, 'combines_with_id');
    }

    /**
     * "Birleşen Devreler": hangi devrenin hangisiyle birleşebileceğini yönetici
     * tanımlar (Madde 5/7 — ardışık en fazla iki devre). Eşleşme aynı tesis ve
     * yıl içinde olmak zorundadır.
     */
    public function canCombineWith(Period $other): bool
    {
        return $this->combines_with_id !== null
            && $this->combines_with_id === $other->id
            && $this->facility_id === $other->facility_id
            && $this->year === $other->year;
    }

    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('start_date', '>=', now()->startOfDay());
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('year')->orderBy('number');
    }
}
