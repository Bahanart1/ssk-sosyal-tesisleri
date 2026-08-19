<?php

namespace App\Models;

use App\Support\SearchText;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class ReservationGuest extends Model
{
    protected $fillable = [
        'reservation_id', 'full_name', 'tc_no', 'birth_date', 'relation',
        'customer_group_id', 'age_category', 'wants_meal', 'id_document_path', 'civil_registry_path',
        'unit_price', 'line_total', 'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (ReservationGuest $guest) {
            $guest->search_index = SearchText::index((string) $guest->full_name, (string) $guest->tc_no);
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'wants_meal' => 'boolean',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public const RELATIONS = [
        'self' => 'Kendisi',
        'spouse' => 'Eşi',
        'child' => 'Çocuğu',
        'parent' => 'Anne / Baba',
        'bride' => 'Gelini',
        'groom' => 'Damadı',
        'grandchild' => 'Torunu',
        'guest' => 'Misafir',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * Yaş grubu devre başlangıç tarihine göre belirlenir (Madde 8/7).
     *
     * 0-5 yaş (5 yaş 11 ay 30 gün) → ücretsiz
     * 6-11 yaş (11 yaş 11 ay 30 gün) → %60
     * 12+ → tam ücret
     */
    public static function categoryFor(CarbonInterface $birthDate, CarbonInterface $periodStart): string
    {
        $age = $birthDate->copy()->startOfDay()->diffInYears($periodStart->copy()->startOfDay());

        return match (true) {
            $age < 6 => 'child_0_5',
            $age < 12 => 'child_6_11',
            default => 'adult',
        };
    }

    public function ageAt(CarbonInterface $date): int
    {
        return (int) $this->birth_date->copy()->startOfDay()->diffInYears($date->copy()->startOfDay());
    }

    public function relationLabel(): string
    {
        return self::RELATIONS[$this->relation] ?? $this->relation;
    }

    public function ageCategoryLabel(): string
    {
        return match ($this->age_category) {
            'child_0_5' => '0-5 yaş',
            'child_6_11' => '6-11 yaş',
            default => '12 yaş üstü',
        };
    }

    /** Yatak işgal eden kişiler: yetişkinler ve 6-11 yaş grubu (Madde 8/5-6). */
    public function occupiesBed(): bool
    {
        return $this->age_category !== 'child_0_5';
    }

    public function maskedTcNo(): string
    {
        return substr($this->tc_no, 0, 3) . str_repeat('*', 5) . substr($this->tc_no, -3);
    }
}
