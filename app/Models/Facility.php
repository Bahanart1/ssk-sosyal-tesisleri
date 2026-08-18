<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'location', 'description', 'image', 'is_active', 'sort_order'];

    /**
     * Tesis fotoğrafları — sigortader.com.tr'den alınıp public/images/tesisler
     * altına webp olarak kaydedildi. İlk görsel kapak olarak kullanılır.
     */
    private const GALLERY = [
        'colakli' => ['colakli-hero', 'colakli-4', 'colakli-1', 'colakli-2', 'colakli-3', 'colakli-6'],
        'gure' => ['gure-hero', 'gure-004', 'gure-002', 'gure-003', 'gure-005', 'gure-001'],
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Kapak görseli; veritabanında özel bir görsel tanımlıysa o kullanılır. */
    public function coverUrl(): string
    {
        if ($this->image) {
            return str_starts_with($this->image, 'http') ? $this->image : asset($this->image);
        }

        $first = self::GALLERY[$this->slug][0] ?? null;

        return $first ? asset("images/tesisler/{$first}.webp") : asset('images/tesisler/colakli-hero.webp');
    }

    /**
     * Tesis galerisi.
     *
     * @return list<string>
     */
    public function galleryUrls(int $limit = 6): array
    {
        $names = array_slice(self::GALLERY[$this->slug] ?? [], 0, $limit);

        return array_map(fn (string $name) => asset("images/tesisler/{$name}.webp"), $names);
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function periods()
    {
        return $this->hasMany(Period::class);
    }

    public function tariffs()
    {
        return $this->hasMany(Tariff::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
