# SSK Sosyal Tesisleri — Kamp Rezervasyon Sistemi

Sigorta Eğitim, Dinlenme ve Sosyal Tesisler Derneği'nin **Çolaklı** ve **Güre** tesisleri için
internet üzerinden müracaat, yönetici değerlendirmesi ve ödeme sistemi.

Telefonla alınan rezervasyonların yerini alır: üye başvurusunu peşinatıyla birlikte
gönderir, yönetici talebi inceleyip **oda tipini, devreyi, kişi listesini ve tutarı
değiştirebilir**, ardından yer tahsisini onaylayarak üyeye bakiye ödemesi için açar.

Kurallar ve ücretler [sigortader.com.tr](https://sigortader.com.tr) üzerinde yayımlanan
**"2026 Yılı Kamp Dönemleri ve Ücretleri"** ile **"Kamp Konaklama Usul ve Esasları"**
belgelerinden birebir alınmıştır.

---

## Kurulum

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

### Demo hesaplar

| Rol | Giriş | Şifre |
|---|---|---|
| Yönetici | `admin@sigortader.com.tr` (`/admin/giris`) | `admin123` |
| Üye · I. Grup | TC `12345678901` | `musteri123` |
| Üye · II. Grup | TC `98765432109` | `musteri123` |
| Üye · aidat borçlu | TC `11122233344` | `musteri123` |
| Misafir · III. Grup | TC `55566677788` | `musteri123` |

---

## Alan kuralları

**Devre** — Pazar girişle başlar, takip eden cumartesi sona erer; 6 gecedir (Madde 7/1).
Üye bir devre veya *birleşen devreler* listesindeki ardışık iki devre (13 gece) için
başvurabilir (Madde 5/7).

**Müşteri grupları** — Her kişi kendi grubuna göre ücretlendirilir:

| Grup | Kapsam |
|---|---|
| I. Grup | Dernek üyesi ve bakmakla yükümlü olduğu aile fertleri |
| II. Grup | Üyenin gelini, damadı ve torunu |
| III. Grup | Dernek üyesi olmayanlar (misafir) |

**Ücret tabloları** — `Tablo 1` oda ücretleri, `Tablo 2` Çolaklı villalarının yemeksiz
ücretleridir. Villa tablosundaki indirimli devre grubu oda tablosundan farklı olduğundan
(villa 1-2-3, oda 1 ve 3) her devre ayrı ayrı oda ve villa tarifesine bağlanır.

---

## Fiyat motoru

`app/Services/Pricing/ReservationPricer.php` tek doğruluk kaynağıdır; müşteri sihirbazının
canlı özeti, yöneticinin düzenleme ekranı ve kayıt anındaki tutar aynı sınıftan geçer.

Kişi başı günlük birim ücret sırasıyla:

1. **Tablo ücreti** — tesis × devre bandı × müşteri grubu
2. **Zemin kat indirimi** — Çolaklı iki kişilik zemin kat odalarında %10
3. **Yaş katsayısı** — 12+ tam · 6-11 yaş %60 · 0-5 yaş ücretsiz (yemek talebinde %40).
   Yaş, devre başlangıç tarihine göre hesaplanır (Madde 8/7).
4. **Müracaat tarihi farkı** — 01.04–30.06 arası +200 ₺, 01.07 sonrası +300 ₺ (kişi/gün)

Üzerine:

- **Villa asgari tutarı** — villalar en az beş kişi üzerinden ücretlendirilir (Madde 8/3)
- **Boş yatak ücreti** — yalnız indirimsiz devrelerde; Güre'de 3 kişilik odada 2 kişi
  konaklarsa alınmaz (Madde 8/9-10)
- **Peşinat** — oda/villa başına 10.000 ₺ (iki devre 20.000 ₺); tek kişi konaklamada yarısı
- **Bakiye vadesi** — tahsis bildiriminden 15 gün; devre başlangıcına daha az kalmışsa
  devre başlangıcı (Madde 8/8)

Onay anında dökümün tamamı `reservations.price_breakdown` alanına yazılır; tarifeler
sonradan değişse de onaylanmış başvurunun tutarı sabit kalır.

Tüm bu kurallar `tests/Feature/PricingTest.php` içinde yayımlanan tablolara karşı doğrulanır.

---

## Ödeme

Peşinat ve bakiye iki kanaldan tahsil edilir:

- **Havale / EFT** — üye dekont yükler, yönetici doğrular (Madde 5/8, 6/4)
- **Kart** — banka sanal POS'u üzerinden, 3D Secure ve taksit desteğiyle

Sanal POS katmanı `App\Services\Payment\PaymentGateway` arayüzü arkasındadır:

| Sürücü | Açıklama |
|---|---|
| `fake` | Uygulama içi 3D Secure benzetimi. Banka bilgisi gerektirmez, akışın tamamı test edilebilir. |
| `nestpay` | NestPay/EST altyapılı banka sanal POS'u (Akbank, İş Bankası, Ziraat, Halkbank). |

Banka anlaşması tamamlandığında `.env` dosyasına terminal bilgileri girilip
`PAYMENT_DRIVER=nestpay` yapılması yeterlidir; akışın geri kalanı değişmez. Farklı bir
altyapı seçilirse yalnızca `PaymentGateway` arayüzünü uygulayan yeni bir sınıf yazılır.

> Şu an `PAYMENT_DRIVER=fake` ile gelir. Ödeme ekranı test ortamı olduğunu açıkça belirtir.

---

## Belge güvenliği

Kimlik belgeleri, banka dekontları ve sağlık raporları kişisel veri içerir. Bu dosyalar
public diske **yazılmaz**; `storage/app/private` altında tutulur ve yalnızca başvuru
sahibine ve yöneticilere, her istekte yetki kontrolünden geçen route'lar üzerinden sunulur
(`App\Http\Controllers\DocumentController`).

---

## Yönetim paneli

| Ekran | İşlev |
|---|---|
| Genel Bakış | Bekleyen talepler, tahsilat, devre doluluğu |
| Başvurular | Filtreleme, detay, **düzenleme ve yer tahsisi onayı** |
| Ödemeler | Havale dekontlarının doğrulanması, POS işlemlerinin izlenmesi |
| Devreler | Devre tarihleri, tarife ataması, başvuruya açma/kapatma |
| Tarifeler | Tablo 1 ve Tablo 2 ücretlerinin düzenlenmesi |
| Tesis & Odalar | Oda tipleri, yatak sayıları, envanter |
| Üyeler | Giriş bilgileri, grup ataması, aidat durumu |
| Parametreler | Peşinat, müracaat farkı kademeleri, çocuk oranları, banka hesapları |

Aidat borcu bulunan üyelerin müracaat formu işleme alınmaz (Madde 5/10); yönetici aidatı
"ödendi" olarak işaretleyerek başvuru hakkını açar.

---

## Testler

```bash
php artisan test
```

- `PricingTest` — fiyat motorunun yayımlanan ücret tablolarıyla birebir uyumu
- `ReservationFlowTest` — başvuru, belge yetkilendirmesi, yönetici düzenlemesi, ödeme akışı
- `ScreensRenderTest` — tüm ekranların hatasız açılması

---

## Kapsam dışı

- SMS bilgilendirmesi (Madde 6/7) ve e-arşiv fatura gönderimi (Madde 9) — dış servis gerektirir
- Yer tahsisi otomatik yapılmaz: müracaat ve peşinat tahsis anlamına gelmediğinden
  (Madde 6/1) sistem yöneticiye doluluk bilgisi sunar, kararı yönetici verir
