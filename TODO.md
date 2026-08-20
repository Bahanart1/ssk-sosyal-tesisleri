# Backend denetimi — açık işler

17 Ağustos 2026 tarihli inceleme. Her madde kod üzerinde doğrulanmıştır;
"temiz çıkanlar" bölümü tekrar incelenmemesi için bilerek bırakılmıştır.

---

## 🔴 Kritik ✅ kapandı

### 1. Oda çifte tahsisi — yarış koşulu

**Yer:** `app/Http/Controllers/Admin/ReservationController.php@assignRoom`

Odanın boş olduğu kontrol ediliyor, ardından ayrı bir sorguyla yazılıyor.
Arada kilit de transaction da yok:

```php
&& Room::whereKey($room->id)->freeForPeriods($periodIds, $reservation->id)->exists();  // kontrol
// ... kilit yok ...
$reservation->update(['room_id' => $secilen['room_id']->id, ...]);                      // yazma
```

**Senaryo:** İki yönetici aynı anda MENEKŞE 3'ü 15. devrede iki farklı başvuruya
tahsis eder. İkisi de kontrolü geçer (henüz kimse yazmamıştır), ikisi de yazar.
Aynı oda iki aileye verilir, sistem uyarmaz; hata tesise girişte ortaya çıkar.

**Veritabanı da korumuyor:** `reservations_room_id_period_id_index` UNIQUE değil.

`RoomAllocationTest` iş mantığını doğru kapsıyor (devre bazlı doluluk, birleşik
devre, iptal serbest bırakma, pasif oda) ancak TOCTOU testle yakalanamaz.

**Yapıldı — 20 Ağustos 2026:**
- [x] Atama `DB::transaction` içine alındı, odalar `lockForUpdate()` ile kilitleniyor
- [x] Uygunluk kontrolü kilit altında tekrarlanıyor
- [x] `(room_id, period_id)` ve `(second_room_id, period_id)` üzerine kısmi unique
      indeks — `2026_08_20_000001_add_room_period_unique_to_reservations`
- [x] Kısıt ihlali yakalanıp kullanıcıya "oda bu arada tahsis edildi" olarak dönüyor
- [x] 3 test: bayat sayfadan tahsis reddi, veritabanı kısıtı, iptalin odayı serbest bırakması

**Not:** SQLite'ta `lockForUpdate` etkisizdir (okuma kilidi yoktur); orada asıl
korumayı kısmi unique indeks sağlar. Kilit MySQL/PostgreSQL'e taşındığında devreye girer.

**Kalan boşluk:** Unique index yalnızca birincil oda/devre çiftini kapsar;
`room_id` ile başka bir başvurunun `second_room_id`'sinin çakışmasını yakalamaz.
Tam çözüm için bkz. madde 5.

---

## 🟡 Önemli ✅ kapandı

### 2. `users` tablosunda isim indeksi yok

10.864 satırda üye listesi `ORDER BY name` ile sayfalanıyor. Sorgu planı:

```
QUERY PLAN
|--SCAN users
`--USE TEMP B-TREE FOR ORDER BY
```

20 kayıt göstermek için her istekte tam tablo taraması ve tam sıralama yapılıyor.
Aidat listesi de aynı sıralamayı kullanıyor. `role` üzerinde de indeks yok.

**Yapıldı — 20 Ağustos 2026:** `2026_08_20_000002_add_role_name_index_to_users`

Sorgu planı `SCAN users` + `USE TEMP B-TREE FOR ORDER BY` →
`SEARCH users USING INDEX users_role_name_index (role=?)`.
Tam tablo taraması ve geçici sıralama ortadan kalktı.

### 3. `RefundController@pay` — aynı sınıf yarış koşulu

`abort_if($refund->isPaid(), 422)` → `markPaid()` arasında kilit yok. Etkisi
düşük (iade iki kez "ödendi" işaretlenir, `reference_no` üzerine yazılır) ama
aynı desenin tekrarı.

**Yapıldı — 20 Ağustos 2026:** `RefundService::markPaid` artık koşulu
güncellemenin kendi `WHERE`'inde taşıyor (`where('status','!=','paid')`); hiçbir
satır etkilenmezse `ValidationException` fırlatıyor. Tek ifadeli atomik güncelleme,
transaction gerektirmiyor. Eşzamanlı ikinci ödeme referans numarasını ezemiyor.

---

## 🟠 Yapısal ✅ kapandı

### 4. FormRequest yerine inline doğrulama

18 controller inline `$request->validate()` kullanıyor; `app/Http/Requests/`
altında yalnızca 1 sınıf var. Kurallar controller'a gömülü olduğu için tek
başına test edilemiyor ve tekrar kullanılamıyor.

**Yapıldı — 20 Ağustos 2026:** 1 → **9 FormRequest sınıfı**.

Taşınanlar (kural sayısına göre): `UpdateSettingsRequest` (25), `UpdateReservationRequest`
(25), `StoreReservationRequest` (17), `StorePeriodRequest` (13),
`UpdateMembershipDueRequest` (12), `UpdateTariffRequest` (10),
`UpdatePeriodRequest` (10), `StoreTariffRequest` (8).

İş kuralları da `after()` kancasına taşındı: kapasite kontrolü, birleşen devre
kontrolü, mükerrer devre numarası. Controller'lar artık yalnızca akışı yönetiyor.

**Bilerek taşınmayanlar:** 1–3 kurallı ~20 çağrı yeri (`reject`, `cancel`,
`markPaid`, `login`, `collect` gibi). Bunlar için ayrı sınıf, okunabilirliği
azaltır. `FacilityController` zaten paylaşılan `roomTypeRules()` metodunu
kullanıyor — makul bir orta yol, dokunulmadı.

### 5. Policy sınıfı yok

`app/Policies` dizini yok; yetkilendirme controller'lara serpiştirilmiş elle
`abort_unless($x->user_id === Auth::id(), 403)` çağrılarıyla yapılıyor.
**Baktığım her yerde doğru yazılmış** — ancak bu opt-in bir model: yeni endpoint
yazan kişi satırı unutursa veri sessizce açılır.

**Yapıldı — 20 Ağustos 2026:** 4 Policy sınıfı (`Reservation`, `MembershipDue`,
`Refund`, `Petition`), temel `Controller`'a `AuthorizesRequests`.

Elle sahiplik kontrolü **0'a indi**; hepsi `$this->authorize()` üzerinden.
`view` (sahip veya yönetici) ve `act` (yalnızca sahip) olarak iki yetenek.

**Not:** Projede daha önce hiç 403 testi yoktu — yetkilendirmenin negatif tarafı
hiç kapsanmamıştı. `AuthorizationTest` bunu kapsıyor; policy otomatik keşfinin
çalıştığını da doğruluyor (aksi halde kural sessizce devre dışı kalabilirdi).

### 6. Oda doluluğu için ayrı tablo (madde 1'in tam çözümü)

Doluluk şu an `reservations` üzerindeki dört sütuna dağılmış durumda
(`room_id`, `second_room_id`, `period_id`, `second_period_id`). Bu yüzden
"bir oda bir devrede tek başvuruya ait olabilir" kuralı tek bir veritabanı
kısıtıyla ifade edilemiyor.

**Yapıldı — 20 Ağustos 2026:** `room_period_occupancies` tablosu,
`UNIQUE(room_id, period_id)`.

Satırlar `ReservationObserver` ile başvurudan türetilir. Observer tercih edildi
çünkü senkron noktaları çok: oda ataması, devre değişikliği, iptal, onay, silme.
Model olayına bağlanınca "bir mutasyon noktasını atlamak" diye bir hata sınıfı
kalmıyor.

`Room::freeForPeriods` artık doluluk tablosundan okuyor — dört sütunlu mantık
tek `whereDoesntHave`'e indi.

**Kapanan boşluk:** Bir başvurunun `room_id`'si ile başkasının `second_room_id`'si
aynı devrede çakışamıyor artık; kısmi indeksin yakalayamadığı durum buydu.
`test_capraz_oda_cakismasi_engellenir` bunu doğruluyor.

---

## 🧹 Refactoring (refactoring.guru kataloğuna göre)

Katalogdaki ~70 tekniğin bu kod tabanında karşılığı ölçüldü; üçü tuttu.

### 7. Duplicate Code → *Extract Class* ✅ yapıldı — 20 Ağustos 2026

`syncQuantities()` iki yerdeydi (`ImportRooms`, `RoomController`) ve kopyalar
ayrışmıştı: komut odası kalmayan tipi pasife alıyor, ekran almıyordu. Müşteri
başvuru ekranı oda tiplerini yalnızca `is_active` ile süzdüğü için, ekrandan son
odası pasife alınan tip **sıfır fiziksel odayla rezervasyona açık kalıyordu.**

- [x] `App\Services\RoomInventory::sync()` — kural tek sahipte
- [x] Her iki çağıran servise bağlandı; hata yapıca kapandı
- [x] Regresyon testi — eski davranışta düştüğü doğrulandı

### 8. Primitive Obsession → *sabit sınıfı* (hafif sürüm) ✅ yapıldı — 20 Ağustos 2026

Sistemde altı ayrı `status` sözlüğü var, kelimeler örtüşüyor: `pending` dört,
`paid` üç farklı sözlükte. `where('status', 'paid')` üç modelde de geçerli SQL,
üçünde farklı anlam, hiçbirinde hata vermiyor.

- [x] `App\Support\ReservationStatus` — sabitler + `OCCUPYING` / `CLOSED` / `ALL`
- [x] Aynı listenin **üç inline kopyası** birleştirildi
      (`Room::OCCUPYING_STATUSES`, `DashboardController::LIVE_STATUSES`,
      `PeriodController` içindeki üçüncüsü)
- [x] Başvuru bağlamındaki ham dizgiler sabitlere çevrildi (9 dosya)

**Bilinçli olarak PHP enum değil:** modele cast eklemek, Blade'deki
`$reservation->status === 'pending'` biçimindeki 30 karşılaştırmayı sessizce
`false`'a düşürürdü — hata fırlatmaz, yanlış rozet gösterirdi. Sabitler aynı
tekliği görünüm riski almadan veriyor.

**Dokunulmayanlar:** `Refund::reason`, `MembershipDue::status`, `Payment::status`
ve `deposit_status` kendi sözlükleri; view'ların tamamı.

### 9. Long Method / Large Class → *Extract Method* ✅ yapıldı — 20 Ağustos 2026

`Admin/ReservationController` 752 satır, 11 public metot; `update()` tek başına
193 satır ve doğrulama → kapasite → kişi senkronu → belge → fiyat → durum geçişi
→ ödeme → iade farkı yapıyor. **Divergent Change**: üç ayrı kural aynı metodu
değiştirtiyor. Projede `ReservationService` zaten var, admin tarafı kullanmıyor.

**Yapıldı — 20 Ağustos 2026:** `update()` **188 → 38 satır**, sınıf **753 → 598**.

- Doğrulama + iş kuralları → `UpdateReservationRequest`
- Kişi senkronu, belge, fiyat → `ReservationService::updateByAdmin()` / `syncGuests()`
- Ödeme farkı → `ReservationService::settleDifference()`, mesaj üretimi controller'da

**Korunan davranış:** İptal edilmiş başvuruda 403 değil 422 dönüyor —
`failedAuthorization()` ile. Refactoring sırasında sessizce değişmişti, testle
sabitlendi.

**Yapılmayacaklar (ölçüldü, bilerek elendi):** `ReservationPricer::quote`
(155 satır ama tek işi yapıyor ve ücret tablolarıyla birebir testli — bölmek
en riskli kodda regresyon getirir), `ImportMembers::handle` (yılda birkaç kez
çalışan testli CLI), kalıtım hiyerarşisi gerektiren teknikler (karşılığı yok).

---

## 👤 Rol yönetimi (spatie/laravel-permission) ✅ yapıldı — 20 Ağustos 2026

İki katman bilinçli olarak ayrı tutuldu:

- **Hesap türü** — `users.role` (admin / customer). Hangi panele girileceğini bu
  belirler; giriş ve `role:` middleware'i buna bakar. Değişmedi.
- **Yetki** — Spatie rolleri. Bir yöneticinin panelde *ne yapabileceğini* belirler.

`role` middleware adı projede zaten kullanıldığı için Spatie'nin `role`/`permission`
alias'ları kaydedilmedi; yetki kontrolü Laravel'in yerleşik `can:` middleware'i
üzerinden yapılıyor. Çakışma yok.

### Roller

| Rol | Kapsam |
|---|---|
| `super-admin` | `Gate::before` ile bütün yetkiler. Listesi tutulmaz — yeni yetki eklendiğinde ona ayrıca vermeyi unutmak imkânsız. |
| `calisan` | Günlük iş: başvuru, ödeme, aidat, dilekçe, tesiste tahsilat, oda envanteri, üye kaydı. |

### Yetkiler

25 yetki, **ekran değil işlem** düzeyinde (`App\Support\Permissions`). 48 rota
`can:` ile korunuyor. Yetkisi olmayan ekranın menü bağlantısı hiç gösterilmiyor.

Geri dönüşü zor işlemler ayrı yetki: `iade.ode`, `aidat.tahakkuk`, `aidat.sil`,
`basvuru.iptal`. Çalışanda **varsayılan olarak kapalı** — Yöneticiler ekranından
kod değişmeden açılabilir.

### Ekran

`/admin/yoneticiler` — hesap ekleme, rol atama, aktif/pasif ve çalışan rolünün
yetki matrisi. Son super admin rolünü bırakamıyor ya da pasife alınamıyor;
aksi halde panele tam yetkiyle kimse giremez ve kilit dışarıdan açılamazdı.

### Test

`RolePermissionTest` — 9 test: çalışanın erişebildikleri, erişemedikleri, riskli
işlemlerin kapalılığı, super admin'in her yetkiyi taşıması, yetkinin sonradan
verilebilmesi, rolsüz yöneticinin yetkisizliği, menü süzgeci, son super admin kilidi.

**Not:** Rolü olmayan yönetici hiçbir şey yapamaz (güvenli varsayılan). Mevcut
yöneticiye `super-admin` atandı; yenileri ekrandan rol seçilerek eklenir.

---

## ✅ Temiz çıkanlar — tekrar incelemeye gerek yok

- **N+1 yok** — tüm liste controller'ları `with()` / `withCount()` kullanıyor
- **Belge güvenliği sağlam** — `authorizeAccess()` her metotta, rotalar `auth`
  altında, private disk, `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`
- **Mass assignment yok** — hiçbir yerde `$request->all()` yok, hepsi validated dizi
- **Para kolonları `decimal`**, float değil
- **Ödeme mükerrerliği** transaction ve testle korunuyor
- **Sayfalamasız `->get()` çağrıları** ya toplulaştırma (≤5 satır) ya da
  devre/tesis filtresiyle sınırlı — ölçek riski değil

---

## Veri tarafında bekleyenler

- [ ] **Güre oda listesi** — dosya gelmedi. Geldiğinde:
      `php artisan ssk:import-rooms "ODALAR.xlsx" --facility=gure`
      Güre'nin 84 odası şu an oda tipi adedinden okunuyor.
- [ ] **21 üye TC'siz** — kütükte tekrar eden (14) veya geçersiz (7) TC taşıyanlar.
      Yönetici TC'yi düzeltene dek giriş yapamazlar.
- [ ] **Aidat ödemeleri** — 2026 tahakkuku açıldı, tahsilat kayıtları girilmedi;
      bu nedenle tüm üyeler borçlu görünüyor ve başvuru yapamıyor (Madde 5/10).
