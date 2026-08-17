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

## Üye paneli

| Sekme | İçerik |
|---|---|
| Panelim | Aidat durumu, başvuru sayısı, bekleyen bakiye; yaklaşan konaklama ve son başvurular |
| Başvurularım | Tüm müracaatlar, duruma göre süzme, bakiye ve son ödeme tarihi |
| Aidatlarım | Yıl bazında tahakkuk/ödeme geçmişi, toplam borç, ödeme için banka hesapları |
| Hesabım | İletişim bilgileri ve şifre değiştirme |

Üye yalnızca **telefon, e-posta ve adres** bilgilerini güncelleyebilir. Ad soyad, TC kimlik
numarası, üyelik numarası, müşteri grubu, hesap durumu ve aidat kayıtları Dernek tarafından
yönetilir; bu alanlar üye tarafından değiştirilemez (form dışından gönderilseler dahi
yok sayılır — `tests/Feature/ReservationFlowTest.php` bunu doğrular). Şifre değişikliği
mevcut şifrenin doğrulanmasını gerektirir.

Aidat tahsilatı Dernek tarafından kaydedilir; üye paneli yalnızca bilgilendirir ve ödeme
için banka hesaplarını gösterir. III. Grup üyeler bu sekmede "aidattan muaf" bilgisini görür.

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
| Devreler | Devre tarihleri, tarife ataması, başvuruya açma/kapatma; devre kartı |
| Tarifeler | Tablo 1 ve Tablo 2 ücretlerinin düzenlenmesi |
| Tesis & Odalar | Oda tipleri, yatak sayıları, ücretlendirme özellikleri |
| Oda Envanteri | Blok ve oda numarasıyla fiziksel odalar; bakımdaki odayı pasife alma |
| Üyeler | Üye kartı: künye, aidat geçmişi, başvurular ve ödemeler |
| Aidatlar | Yıllık tahakkuk ve tahsilat defteri |
| Parametreler | Peşinat, müracaat farkı kademeleri, çocuk oranları, aidat tutarı, banka hesapları |

### Devre kartı

Devreler listesinde bir devreye tıklandığında o devrenin kartı açılır:

- **Yer tahsis edilen üyeler** — onaylanmış ve ödemesi tamamlanmış başvurular; kişi sayısı,
  tutar ve kalan bakiyeyle birlikte
- **İnceleme bekleyen başvurular** — talep gönderilmiş, karar verilmemiş olanlar; peşinat
  durumu ve üyenin aidat durumu yan yana, doğrudan "Değerlendir" bağlantısıyla
- **Konaklayacak kişiler** — tahsis edilen başvurulardaki kişilerin tamamı; tesise giriş
  listesi olarak kullanılır (TC no, doğum tarihi, yaş grubu, kimlik belgesi)
- **Oda tipi dağılımı** — envanter, tahsis edilen, bekleyen ve kalan ünite sayısı

Birleşik devre başvuruları her iki devrenin kartında da görünür.

### Üyelik aidatı

Aidat, üye başına **yıl yıl tahakkuk** kaydı olarak tutulur (`membership_dues`): tutar,
durum (borçlu / ödendi / muaf), ödeme tarihi, yöntem ve makbuz no. Böylece "hangi üye
hangi yılı ödedi" sorusu tek ekrandan yanıtlanır.

- **Aidatlar** ekranı seçilen yılın tahakkuk, tahsilat ve kalan borç özetini gösterir;
  tek tıkla tahsilat işaretlenir, toplu tahakkuk açılır.
- **Üye kartı** (Üyeler → bir üyeye tıklayın) o üyenin tüm yıllarını, başvurularını ve
  ödemelerini birlikte gösterir.
- İçinde bulunulan yıl dahil ödenmemiş tahakkuku olan üyenin müracaat formu işleme
  alınmaz (Madde 5/10). Vadesi gelmemiş (gelecek yıl) tahakkuk borç sayılmaz.
- III. Grup (dernek üyesi olmayan misafirler) aidattan muaftır.
- Üye, kendi panelinde borçlu olduğu yılları ve toplam tutarı görür.

### Genel bakış ekranı

Tahsilat seyri, devre doluluğu, müşteri grubu ve oda tipi dağılımı sunucuda üretilen
satır içi SVG/HTML grafiklerle çizilir — harici grafik kütüphanesi yoktur. Her grafiğin
başlığında **Grafik / Tablo** değiştiricisi bulunur, böylece aynı veri sayısal olarak da
okunabilir.

Renkler ölçülerek seçilmiştir, göz kararıyla değil:

- Seri rengi — açık modda `#2f6cb0` (5.4:1), koyu modda `#5b9bd8` (5.6:1)
- Sıralı rampa yalnızca **sıralı** veride kullanılır (I./II./III. Grup); sıra rengin
  açıklığıyla taşınır. Her iki mod için ayrı ayrı doğrulanmıştır (monoton açıklık,
  adım aralığı ≥ 0.06, açık uç ≥ 2:1)
- Devre doluluğu gibi **tek serili** grafiklerde tüm sütunlar aynı renktedir: yüksekliğin
  zaten gösterdiği değeri renkle ikinci kez kodlamak bilgi taşımaz
- Durum renkleri (ödendi / borçlu / muaf) ayrılmış anlamdadır, daima etiketle birlikte
  kullanılır ve seri renkleriyle karıştırılmaz

---

## Tema ve renk sistemi

Arayüz **çelik mavisi + soğuk nötr gri** üzerine kuruludur ve **açık/koyu tema**
destekler. Renkler iki katmanlıdır:

| Katman | Nerede tanımlı | Örnek |
|---|---|---|
| Sabit kimlik ölçekleri | `tailwind.config.js` | `accent-600`, `navy-900` |
| Semantik tokenlar | `resources/css/app.css` | `bg-surface`, `text-ink`, `border-line` |

Karanlık mod tek yerden döner: `<html class="dark">` eklendiğinde `app.css` içindeki
CSS değişkenleri koyu değerleriyle değişir. Bu nedenle arayüzde her sınıfa ayrı bir
`dark:` karşılığı yazmak gerekmez — `bg-surface` her iki modda doğru zemini verir.

Tema seçimi `localStorage`'a yazılır; kullanıcı hiç seçim yapmadıysa işletim sistemi
ayarı izlenir. Tercih, sayfa boyanmadan önce `partials/head.blade.php` içindeki küçük
bir betikle uygulanır, böylece açılışta tema titremesi olmaz.

## Kütük aktarımı

Dernek kayıtları Excel dosyalarından aktarılır. Her iki komut da yeniden
çalıştırılabilir ve önce `--dry-run` ile önizlenebilir.

### Üye listesi

```bash
php artisan ssk:import-members "AKTİF ÜYE LİSTESİ.xlsx" --dry-run
php artisan ssk:import-members "AKTİF ÜYE LİSTESİ.xlsx" --rounds=10
```

Beklenen sütunlar: `ÜYE NO · T.C. NO · AD · SOYAD · DOĞ.TARİH · CEP TELEFON ·
ÜYE TARİHİ · Ç.İLİ · KURUM`. Sütunlar konuma göre değil başlık adına göre
eşlenir; başlıktan önceki kapak satırları atlanır.

- Eşleştirme anahtarı **üye numarasıdır**. Yeniden çalıştırıldığında mevcut
  üyelerin kütük alanları güncellenir, **şifreleri korunur**.
- Başlangıç şifresi üyenin **TC kimlik numarasıdır**; giriş de TC ile yapılır.
- `--rounds` yalnızca aktarımı hızlandırır — on binlerce bcrypt özeti varsayılan
  maliyetle yarım saat sürer. Laravel, düşük maliyetli özeti ilk başarılı
  girişte yapılandırılmış maliyete kendiliğinden yükseltir.
- `tc_no` tekil olduğundan, kütükte tekrar eden ya da 11 haneli olmayan TC
  taşıyan üyeler **TC'siz** aktarılır: kayıtları durur, ancak yönetici TC'yi
  düzeltene dek giriş yapamazlar. Komut bunların tamamını raporlar.
- Aidat tahakkuku üretilmez; borç kaydı olmayan üye borçsuz sayılır (Madde 5/10).

### Oda listesi

```bash
php artisan ssk:import-rooms "ODALAR.xlsx" --facility=colakli --dry-run
php artisan ssk:import-rooms "ODALAR.xlsx" --facility=colakli --prune
```

Dosya, her bloğun yan yana üç sütunla (`BLOK · ODA NO · tip`) verildiği bir
ızgaradır. Blok grupları `BLOK` başlıklarının bulunduğu sütunlardan saptanır.

- Oda tipi, **yatak sayısı** ve bloğun **zemin katta** olup olmadığından çözülür:
  `NERGİS ZEMİN` bloğundaki bir `ÇİFT KİŞİLİK` oda, iki yataklı zemin kat tipine
  bağlanır (%10 indirim). Şemada karşılığı olmayan tipler
  `ImportRooms::TYPE_CATALOG` tanımından oluşturulur.
- Aynı ad birden çok sütun grubunda geçerse ayrı bloklar sayılır ve
  `KARANFİL A` / `KARANFİL B` biçiminde adlandırılır.
- `room_types.quantity` fiziksel envanterden yeniden hesaplanır. Hiç odası
  kalmayan oda tipi rezervasyona açık kalmasın diye pasife alınır
  (`--keep-empty-types` ile korunabilir).
- `--prune`, dosyada bulunmayan mevcut odaları siler.

Aktarım sonrası odalar **Oda Envanteri** ekranından yönetilir. Envanteri henüz
girilmemiş bir tesiste kapasite, oda tiplerindeki adet değerinden okunmaya devam
eder; envanter girildiği anda adet oradan türetilir.

Villalar oda listesinde yer almaz; envanterleri `FacilitySeeder`'daki adetten
gelir ve aktarım sırasında değiştirilmez.

### Demo veri (isteğe bağlı)

Grafiklerin dolu görünmesi için örnek başvuru üreten seeder varsayılan seed'e **dahil
değildir**; yalnızca tanıtım ortamında çalıştırın:

```bash
php artisan db:seed --class=DemoReservationSeeder
```

---

## Testler

```bash
php artisan test
```

- `PricingTest` — fiyat motorunun yayımlanan ücret tablolarıyla birebir uyumu
- `ImportTest` — üye ve oda kütüklerinin aktarımı (test içinde .xlsx üretilir)
- `ReservationFlowTest` — başvuru, belge yetkilendirmesi, yönetici düzenlemesi, ödeme akışı
- `ScreensRenderTest` — tüm ekranların hatasız açılması

---

## Kapsam dışı

- SMS bilgilendirmesi (Madde 6/7) ve e-arşiv fatura gönderimi (Madde 9) — dış servis gerektirir
- Yer tahsisi otomatik yapılmaz: müracaat ve peşinat tahsis anlamına gelmediğinden
  (Madde 6/1) sistem yöneticiye doluluk bilgisi sunar, kararı yönetici verir
