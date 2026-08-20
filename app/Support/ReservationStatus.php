<?php

namespace App\Support;

/**
 * Başvuru durumlarının tek sahibi.
 *
 * Sistemde altı ayrı "status" sözlüğü var ve kelimeler örtüşüyor: 'pending'
 * dört, 'paid' üç farklı sözlükte, her birinde başka anlamda geçiyor. Ham
 * dizgiyle yazılan bir sorgu (`where('status', 'paid')`) üç modelde de geçerli
 * SQL üretir, üçünde farklı iş anlamı taşır ve hiçbirinde hata vermez.
 * Bu sınıf, başvuruya ait sözlüğü adlandırarak o karışıklığı önler.
 *
 * Bilinçli olarak PHP enum değildir: modele cast eklenmesi, Blade'deki
 * `$reservation->status === 'pending'` biçimindeki 30 karşılaştırmayı sessizce
 * false'a düşürürdü. Sabitler aynı tekliği hiçbir görünüm riski almadan verir.
 */
final class ReservationStatus
{
    /** Karar bekliyor; yer tahsisi yapılmamış. */
    public const PENDING = 'pending';

    /** Yer tahsis edilmiş; bakiye ödemesi bekleniyor. */
    public const APPROVED = 'approved';

    /** Ödemesi tamamlanmış. */
    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /**
     * Odayı fiilen işgal eden durumlar.
     *
     * Bu üçü dışındaki başvurular odayı serbest bırakır; oda uygunluğu ve
     * doluluk hesapları bu listeye dayanır.
     *
     * @var list<string>
     */
    public const OCCUPYING = [self::PENDING, self::APPROVED, self::PAID];

    /**
     * Sonuçlanmış, artık işlem görmeyen durumlar.
     *
     * @var list<string>
     */
    public const CLOSED = [self::REJECTED, self::CANCELLED];

    /** @var list<string> */
    public const ALL = [
        self::PENDING,
        self::APPROVED,
        self::PAID,
        self::REJECTED,
        self::CANCELLED,
    ];

    /** Doğrulama kuralı: `Rule::in(ReservationStatus::ALL)` yerine kısa yol. */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::ALL);
    }
}
