<?php

namespace App\Support;

/**
 * Panel yetkilerinin tek sahibi.
 *
 * Hesap türü (yönetici / üye) `users.role` sütununda kalır ve panel ayrımını o
 * belirler. Buradaki yetkiler ise bir yöneticinin panelde *ne yapabileceğini*
 * tanımlar. İki katman bilinçli olarak ayrıdır.
 *
 * Yetkiler ekran değil **işlem** düzeyindedir: böylece "çalışan aidatı görsün
 * ama tahakkuk açmasın" gibi ayrımlar kod değişmeden yapılabilir.
 */
final class Permissions
{
    // --- Başvurular ---
    public const BASVURU_GOR = 'basvuru.gor';

    public const BASVURU_DUZENLE = 'basvuru.duzenle';

    public const BASVURU_KARAR = 'basvuru.karar';          // onay / red

    public const BASVURU_IPTAL = 'basvuru.iptal';          // riskli

    public const ODA_ATA = 'basvuru.oda-ata';

    // --- Ödemeler ---
    public const ODEME_GOR = 'odeme.gor';

    public const DEKONT_DOGRULA = 'odeme.dekont-dogrula';

    public const TESISTE_TAHSILAT = 'odeme.tesiste-tahsilat';

    // --- İadeler ---
    public const IADE_GOR = 'iade.gor';

    public const IADE_ODE = 'iade.ode';                    // riskli

    // --- Aidatlar ---
    public const AIDAT_GOR = 'aidat.gor';

    public const AIDAT_TAHSIL = 'aidat.tahsil';

    public const AIDAT_DUZENLE = 'aidat.duzenle';

    public const AIDAT_TAHAKKUK = 'aidat.tahakkuk';        // riskli — toplu

    public const AIDAT_SIL = 'aidat.sil';                  // riskli

    // --- Dilekçeler ---
    public const DILEKCE_GOR = 'dilekce.gor';

    public const DILEKCE_YANITLA = 'dilekce.yanitla';

    // --- Üyeler ---
    public const UYE_GOR = 'uye.gor';

    public const UYE_DUZENLE = 'uye.duzenle';

    // --- Envanter ve tanımlar ---
    public const ODA_ENVANTERI = 'envanter.oda';

    public const TESIS_YONET = 'tanim.tesis';

    public const DEVRE_YONET = 'tanim.devre';

    public const TARIFE_YONET = 'tanim.tarife';

    // --- Sistem ---
    public const PARAMETRE_YONET = 'sistem.parametre';

    public const KULLANICI_YONET = 'sistem.kullanici';

    /**
     * Yetkiler ve insan okunur adları — yetki ekranı bu listeden üretilir.
     *
     * @return array<string, array<string, string>> bölüm => [yetki => ad]
     */
    public static function grouped(): array
    {
        return [
            'Başvurular' => [
                self::BASVURU_GOR => 'Başvuruları görüntüle',
                self::BASVURU_DUZENLE => 'Başvuruyu düzenle',
                self::BASVURU_KARAR => 'Yer tahsisi / red kararı ver',
                self::ODA_ATA => 'Oda tahsis et',
                self::BASVURU_IPTAL => 'Başvuruyu iptal et',
            ],
            'Ödemeler' => [
                self::ODEME_GOR => 'Ödemeleri görüntüle',
                self::DEKONT_DOGRULA => 'Havale dekontunu doğrula',
                self::TESISTE_TAHSILAT => 'Tesiste tahsilat işle',
            ],
            'İadeler' => [
                self::IADE_GOR => 'İadeleri görüntüle',
                self::IADE_ODE => 'İadeyi ödendi işaretle',
            ],
            'Aidatlar' => [
                self::AIDAT_GOR => 'Aidatları görüntüle',
                self::AIDAT_TAHSIL => 'Aidat tahsilatı işle',
                self::AIDAT_DUZENLE => 'Aidat kaydını düzenle',
                self::AIDAT_TAHAKKUK => 'Toplu tahakkuk aç',
                self::AIDAT_SIL => 'Aidat kaydını sil',
            ],
            'Dilekçeler' => [
                self::DILEKCE_GOR => 'Dilekçeleri görüntüle',
                self::DILEKCE_YANITLA => 'Dilekçeyi yanıtla',
            ],
            'Üyeler' => [
                self::UYE_GOR => 'Üyeleri görüntüle',
                self::UYE_DUZENLE => 'Üye kaydı ekle / düzenle',
            ],
            'Envanter ve tanımlar' => [
                self::ODA_ENVANTERI => 'Oda envanterini yönet',
                self::TESIS_YONET => 'Tesis ve oda tiplerini yönet',
                self::DEVRE_YONET => 'Devreleri yönet',
                self::TARIFE_YONET => 'Tarifeleri yönet',
            ],
            'Sistem' => [
                self::PARAMETRE_YONET => 'Parametreleri yönet',
                self::KULLANICI_YONET => 'Yönetici hesaplarını ve yetkileri yönet',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::grouped())));
    }

    /**
     * Çalışan rolünün varsayılan yetkileri: günlük iş.
     *
     * Tanımlar, parametreler ve geri dönüşü zor para işlemleri dışarıda bırakıldı.
     * Bu liste yalnızca **başlangıç** değeridir; super admin, Roller ekranından
     * kod değişmeden değiştirebilir.
     *
     * @return list<string>
     */
    public static function defaultsForStaff(): array
    {
        return [
            self::BASVURU_GOR, self::BASVURU_DUZENLE, self::BASVURU_KARAR, self::ODA_ATA,
            self::ODEME_GOR, self::DEKONT_DOGRULA, self::TESISTE_TAHSILAT,
            self::IADE_GOR,
            self::AIDAT_GOR, self::AIDAT_TAHSIL, self::AIDAT_DUZENLE,
            self::DILEKCE_GOR, self::DILEKCE_YANITLA,
            self::UYE_GOR, self::UYE_DUZENLE,
            self::ODA_ENVANTERI,
        ];
    }
}
