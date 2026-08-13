<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Yönetim Kurulunca her yıl belirlenen parametreler (peşinat, geç müracaat farkı,
 * çocuk oranları, boş yatak, banka hesapları vb.) admin panelinden düzenlenebilir.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'label'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    private const CACHE_KEY = 'settings.all';

    /** @return array<string, mixed> */
    public static function all_values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->pluck('value', 'key')
            ->map(fn ($v) => is_array($v) && array_key_exists('_', $v) ? $v['_'] : $v)
            ->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_values()[$key] ?? $default;
    }

    public static function number(string $key, float $default = 0): float
    {
        return (float) static::get($key, $default);
    }

    public static function put(string $key, mixed $value, string $group = 'genel', ?string $label = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? $value : ['_' => $value], 'group' => $group, 'label' => $label]
        );

        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
