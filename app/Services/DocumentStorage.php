<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kimlik belgeleri, banka dekontları ve sağlık raporları kişisel veri içerir.
 * Bu belgeler public diske yazılmaz; "local" diskin kökü storage/app/private'dır
 * ve dosyalar yalnızca yetki kontrolünden geçen route'lar üzerinden sunulur.
 */
class DocumentStorage
{
    public const DISK = 'local';

    public const IDENTITY = 'identity';
    public const RECEIPT = 'receipts';
    public const HEALTH_REPORT = 'health-reports';
    public const CIVIL_REGISTRY = 'civil-registry';

    /** Yükleme doğrulama kuralları — form request'lerde yeniden kullanılır. */
    public const RULES = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

    public function store(UploadedFile $file, string $category, int|string $ownerKey): string
    {
        $name = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return $file->storeAs("{$category}/{$ownerKey}", $name, self::DISK);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Belgeyi tarayıcıda gösterilmek üzere döndürür. Çağıran taraf yetkilendirmeyi
     * yapmış olmalıdır.
     */
    public function response(string $path, ?string $downloadName = null): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $downloadName, [
            'Content-Disposition' => 'inline; filename="' . ($downloadName ?? basename($path)) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function delete(?string $path): void
    {
        if ($this->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
