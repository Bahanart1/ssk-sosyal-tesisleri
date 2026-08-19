<?php

namespace App\Support;

/**
 * Arama metnini Türkçeye duyarlı biçimde sadeleştirir.
 *
 * Veritabanı LIKE'ı yalnızca ASCII harfleri büyük/küçük eşlediği için "ŞAHİN"
 * ile "şahin" farklı sayılıyordu. Hem kayıtlar hem sorgu aynı kurala göre
 * katlanır: Türkçe harfler ASCII karşılığına iner ve küçük harfe çevrilir.
 * Bu sayede "yilmaz" yazan da "Yılmaz" kaydını bulur.
 */
class SearchText
{
    private const MAP = [
        'ı' => 'i', 'İ' => 'i', 'I' => 'i', 'i' => 'i',
        'ş' => 's', 'Ş' => 's',
        'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u',
        'ö' => 'o', 'Ö' => 'o',
        'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'Â' => 'a',
        'î' => 'i', 'Î' => 'i',
        'û' => 'u', 'Û' => 'u',
    ];

    public static function fold(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = strtr($value, self::MAP);
        $value = mb_strtolower($value, 'UTF-8');

        // Noktalama ve fazla boşlukları tek boşluğa indir.
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * Sorguyu kelimelere ayırır; her kelime ayrı ayrı aranır ki "yilmaz ahmet"
     * de "Ahmet Yılmaz" kaydını bulsun.
     *
     * @return list<string>
     */
    public static function tokens(?string $value): array
    {
        $folded = self::fold($value);

        return $folded === '' ? [] : array_values(array_filter(explode(' ', $folded)));
    }

    /** Birden çok alanı tek bir aranabilir metinde birleştirir. */
    public static function index(string ...$parts): string
    {
        return self::fold(implode(' ', array_filter($parts)));
    }
}
