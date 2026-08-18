<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Türkiye IBAN'ı: TR + 24 rakam, ISO 13616 mod-97 sağlamasıyla.
 * Yanlış yazılmış bir IBAN'a yapılan havale geri dönmediği için doğrulama
 * sağlama basamağına kadar yapılır.
 */
class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = self::normalize((string) $value);

        if (! preg_match('/^TR\d{24}$/', $iban)) {
            $fail('IBAN, TR ile başlayıp 24 rakam içermelidir (toplam 26 karakter).');

            return;
        }

        if (! self::checksumValid($iban)) {
            $fail('IBAN sağlaması tutmuyor. Numarayı bankanızdan kontrol edip yeniden girin.');
        }
    }

    /** Boşluk ve küçük harfleri temizler. */
    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', $value) ?? '');
    }

    private static function checksumValid(string $iban): bool
    {
        // İlk dört karakter sona alınır, harfler sayıya çevrilir (A=10 … Z=35).
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        $numeric = '';

        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // Sayı 64 bit'e sığmadığı için parça parça mod alınır.
        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ((string) $remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }
}
