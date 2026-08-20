<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\MembershipDue;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use App\Services\DocumentStorage;
use App\Support\ReservationStatus;
use Carbon\Carbon;
use Database\Seeders\Camp2026Seeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Period $period;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        // Devre tarihleri 2026 yılına ait olduğundan zaman sabitlenir.
        Carbon::setTestNow('2026-08-13');

        Storage::fake(DocumentStorage::DISK);

        $this->seed([
            SettingSeeder::class,
            CustomerGroupSeeder::class,
            FacilitySeeder::class,
            Camp2026Seeder::class,
        ]);

        $colakli = Facility::where('slug', 'colakli')->firstOrFail();

        $this->period = Period::where('facility_id', $colakli->id)->where('number', 15)->firstOrFail();
        $this->roomType = RoomType::where('facility_id', $colakli->id)->where('code', 'colakli-2-kisilik')->firstOrFail();

        $this->member = User::create([
            'name' => 'Ahmet Yılmaz',
            'tc_no' => '12345678901',
            'membership_no' => 'U-1042',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => $this->groupId('I'),
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function groupId(string $code): int
    {
        return CustomerGroup::where('code', $code)->value('id');
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Yönetici',
            'email' => 'admin@example.com',
            'password' => Hash::make('sifre123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_her_kisi_icin_vukuatli_nufus_kaydi_zorunludur(): void
    {
        $payload = $this->payload();
        unset($payload['guests'][1]['civil_registry']);

        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $payload)
            ->assertSessionHasErrors('guests.1.civil_registry');

        $this->assertSame(0, Reservation::count());
    }

    public function test_vukuatli_nufus_kaydi_kisi_basina_kaydedilir_ve_yalnizca_yetkiliye_acilir(): void
    {
        $reservation = $this->createReservation();
        $guest = $reservation->guests()->firstOrFail();

        $this->assertCount(2, $reservation->guests);
        foreach ($reservation->guests as $kisi) {
            $this->assertNotNull($kisi->civil_registry_path, 'Her kişinin belgesi kaydedilmeli');
            Storage::disk('local')->assertExists($kisi->civil_registry_path);
        }

        // Sahibi ve yönetici görebilir
        $this->actingAs($this->member)
            ->get(route('documents.civil-registry', $guest))
            ->assertOk();

        $admin = User::create([
            'name' => 'Yönetici',
            'email' => 'yonetici@example.test',
            'password' => Hash::make('sifre123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($admin)
            ->get(route('documents.civil-registry', $guest))
            ->assertOk();

        // Başka bir üye göremez
        $yabanci = User::create([
            'name' => 'Yabancı Üye',
            'tc_no' => '99988877766',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => $this->groupId('I'),
            'is_active' => true,
        ]);

        $this->actingAs($yabanci)
            ->get(route('documents.civil-registry', $guest))
            ->assertForbidden();
    }

    public function test_gecersiz_bicimdeki_nufus_kaydi_reddedilir(): void
    {
        $payload = $this->payload();
        $payload['guests'][0]['civil_registry'] = UploadedFile::fake()->create('kayit.docx', 100);

        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $payload)
            ->assertSessionHasErrors('guests.0.civil_registry');
    }

    public function test_yonetici_onaydan_sonra_kisi_ekleyebilir_ve_fark_hesaplanir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation), ['admin_note' => 'Tamam']);
        $reservation->refresh();

        $oncekiTutar = (float) $reservation->total_price;
        $oncekiKisi = $reservation->guests()->count();

        // Üçüncü kişi 2 kişilik odaya sığmaz; yönetici oda tipini de büyütür
        $dortKisilik = RoomType::where('facility_id', $reservation->facility_id)
            ->where('bed_count', 4)->firstOrFail();

        $payload = $this->adminPayload($reservation);
        $payload['room_type_id'] = $dortKisilik->id;
        $payload['guests']['yeni-1'] = [
            'full_name' => 'Sonradan Eklenen',
            'tc_no' => '12345678909',
            'birth_date' => '1992-05-05',
            'relation' => 'child',
            'customer_group_id' => $this->groupId('I'),
        ];

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();

        $this->assertSame($oncekiKisi + 1, $reservation->guests()->count());
        $this->assertGreaterThan($oncekiTutar, (float) $reservation->total_price, 'Kişi eklenince tutar artmalı');
    }

    public function test_yonetici_odenmis_rezervasyondan_kisi_cikarabilir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        $reservation->update(['status' => 'paid']);

        $payload = $this->adminPayload($reservation);
        $ilkKisi = array_key_first($payload['guests']);
        unset($payload['guests'][$ilkKisi]);

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $reservation->fresh()->guests()->count());
    }

    public function test_yonetici_uye_adina_rezervasyon_olusturabilir(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.reservations.create', ['uye' => $this->member->id]))
            ->assertOk()
            ->assertSee($this->member->name);

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), [
                'user_id' => $this->member->id,
                'room_type_id' => $this->roomType->id,
                'period_id' => $this->period->id,
                'guests' => [
                    [
                        'full_name' => 'Telefonla Kaydeden',
                        'tc_no' => '12345678901',
                        'birth_date' => '1980-03-03',
                        'relation' => 'self',
                        'customer_group_id' => $this->groupId('I'),
                    ],
                ],
                'note' => 'Telefonla alındı',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $reservation = Reservation::latest('id')->firstOrFail();

        $this->assertSame($this->member->id, $reservation->user_id);
        $this->assertSame('pending', $reservation->status);
        $this->assertSame(1, $reservation->guests()->count());
        $this->assertStringContainsString('Yönetici tarafından', (string) $reservation->admin_note);
    }

    public function test_yonetici_kapasiteyi_asan_kayit_olusturamaz(): void
    {
        $admin = $this->makeAdmin();

        $kisiler = [];
        foreach (range(1, 3) as $i) {
            $kisiler[] = [
                'full_name' => "Kişi {$i}",
                'tc_no' => '1234567890'.$i,
                'birth_date' => '1990-01-01',
                'relation' => 'child',
                'customer_group_id' => $this->groupId('I'),
            ];
        }

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), [
                'user_id' => $this->member->id,
                'room_type_id' => $this->roomType->id,
                'period_id' => $this->period->id,
                'guests' => $kisiler,
            ])
            ->assertSessionHasErrors('guests');
    }

    /**
     * Yönetici kişi eklediğinde tutar artar ve fark üyeden tahsil edilir;
     * kişi çıkardığında boş kalan yatağın ücreti hesaba katılır.
     */
    public function test_kisi_eklenince_fark_uyeden_tahsil_edilir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        // Ödemesi tamamlanmış, kesinleşmiş bir konaklama
        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation), ['admin_note' => 'Tamam']);
        $reservation->refresh();

        Payment::create([
            'reservation_id' => $reservation->id, 'kind' => 'balance', 'method' => 'bank_transfer',
            'amount' => $reservation->balanceDue(), 'status' => 'success',
            'reference_no' => Payment::newReference(),
        ]);
        $reservation->refresh();

        $this->assertSame(0.0, $reservation->balanceDue(), 'Başlangıçta bakiye kalmamalı');
        $oncekiTutar = (float) $reservation->total_price;

        // 4 kişilik odaya geçip üçüncü kişiyi ekle
        $dortKisilik = RoomType::where('facility_id', $reservation->facility_id)->where('bed_count', 4)->firstOrFail();

        $payload = $this->adminPayload($reservation);
        $payload['room_type_id'] = $dortKisilik->id;
        $payload['guests']['yeni-1'] = [
            'full_name' => 'Sonradan Katılan',
            'tc_no' => '12345678909',
            'birth_date' => '1995-06-06',
            'relation' => 'child',
            'customer_group_id' => $this->groupId('I'),
        ];

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();

        $this->assertSame(3, $reservation->guests()->count());
        $this->assertGreaterThan($oncekiTutar, (float) $reservation->total_price);
        $this->assertGreaterThan(0, $reservation->balanceDue(), 'Eklenen kişinin ücreti tahsil edilecek');
    }

    public function test_kisi_cikarilinca_bos_yatak_ucreti_hesaplanir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        // 4 kişilik odada 4 kişi: boş yatak yok
        $dortKisilik = RoomType::where('facility_id', $reservation->facility_id)->where('bed_count', 4)->firstOrFail();

        $payload = $this->adminPayload($reservation);
        $payload['room_type_id'] = $dortKisilik->id;
        foreach ([1, 2] as $i) {
            $payload['guests']["yeni-{$i}"] = [
                'full_name' => "Ek Kişi {$i}",
                'tc_no' => '1234567891'.$i,
                'birth_date' => '1990-01-01',
                'relation' => 'child',
                'customer_group_id' => $this->groupId('I'),
            ];
        }

        $this->actingAs($admin)->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertSame(4, $reservation->guests()->count());
        $this->assertSame(0, (int) $reservation->empty_bed_count, 'Oda dolu, boş yatak olmamalı');

        // Şimdi bir kişi çıkar — boş yatak ücreti devreye girmeli
        $payload = $this->adminPayload($reservation);
        $payload['room_type_id'] = $dortKisilik->id;
        $sonKisi = array_key_last($payload['guests']);
        unset($payload['guests'][$sonKisi]);

        $this->actingAs($admin)->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();

        $this->assertSame(3, $reservation->guests()->count());
        $this->assertSame(1, (int) $reservation->empty_bed_count, 'Kalan yatak boş yatak sayılmalı');
        $this->assertGreaterThan(0, (float) $reservation->empty_bed_total, 'Boş yatak ücreti hesaplanmalı');
    }

    /**
     * Yöneticinin tek işi "Ödemeyi Üyeye Gönder"dir: ücret sistemce hesaplanır,
     * fark çıkarsa rezervasyon yeniden ödemeye açılır, üye notu ve tutarı görür.
     */
    public function test_odemeyi_gonder_fiyati_sistem_hesaplar_uye_notu_ve_tutari_gorur(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        // Ödemesi tamamlanmış rezervasyon
        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation), ['admin_note' => 'Tamam']);
        $reservation->refresh();
        Payment::create([
            'reservation_id' => $reservation->id, 'kind' => 'balance', 'method' => 'bank_transfer',
            'amount' => $reservation->balanceDue(), 'status' => 'success', 'reference_no' => Payment::newReference(),
        ]);
        $reservation->refresh()->update(['status' => 'paid']);

        // Kişi ekle + not yaz; ücret alanları hiç gönderilmez — sistem hesaplar
        $dortKisilik = RoomType::where('facility_id', $reservation->facility_id)->where('bed_count', 4)->firstOrFail();

        $payload = $this->adminPayload($reservation);
        unset($payload['surcharge_per_person_day'], $payload['adjustment_amount']);
        $payload['room_type_id'] = $dortKisilik->id;
        $payload['action'] = 'send_payment';
        $payload['admin_note'] = 'Talebiniz üzerine eşiniz rezervasyona eklendi.';
        $payload['guests']['yeni-1'] = [
            'full_name' => 'Eklenen Eş',
            'tc_no' => '12345678909',
            'birth_date' => '1990-02-02',
            'relation' => 'spouse',
            'customer_group_id' => $this->groupId('I'),
        ];

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();

        // Ödeme yeniden üyeye açıldı, fark tutarı sistemce hesaplandı
        $this->assertSame('approved', $reservation->status);
        $this->assertGreaterThan(0, $reservation->balanceDue());

        // Üye notu ve ödeyeceği tutarı görür
        $this->actingAs($this->member)
            ->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Talebiniz üzerine eşiniz rezervasyona eklendi.')
            ->assertSee('Ödenecek bakiye');
    }

    public function test_fark_yoksa_odeme_acilmaz(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation), ['admin_note' => 'Tamam']);
        $reservation->refresh();
        Payment::create([
            'reservation_id' => $reservation->id, 'kind' => 'balance', 'method' => 'bank_transfer',
            'amount' => $reservation->balanceDue(), 'status' => 'success', 'reference_no' => Payment::newReference(),
        ]);
        $reservation->refresh()->update(['status' => 'paid']);

        // Hiçbir şey değiştirmeden gönder
        $payload = $this->adminPayload($reservation);
        unset($payload['surcharge_per_person_day'], $payload['adjustment_amount']);
        $payload['action'] = 'send_payment';

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();

        $this->assertSame('paid', $reservation->status, 'Fark yoksa rezervasyon ödenmiş kalmalı');
        $this->assertSame(0.0, $reservation->balanceDue());
    }

    public function test_yonetici_ekledigi_kisiye_belge_yukleyebilir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        $dortKisilik = RoomType::where('facility_id', $reservation->facility_id)->where('bed_count', 4)->firstOrFail();

        $payload = $this->adminPayload($reservation);
        $payload['room_type_id'] = $dortKisilik->id;
        $payload['guests']['yeni-1'] = [
            'full_name' => 'Belgeli Yeni Kişi',
            'tc_no' => '12345678909',
            'birth_date' => '1992-05-05',
            'relation' => 'child',
            'customer_group_id' => $this->groupId('I'),
            'document' => UploadedFile::fake()->image('kimlik-yeni.jpg'),
            'civil_registry' => UploadedFile::fake()->image('nufus-yeni.jpg'),
        ];

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $yeni = $reservation->fresh()->guests()->where('full_name', 'Belgeli Yeni Kişi')->firstOrFail();

        $this->assertNotNull($yeni->id_document_path, 'Kimlik belgesi kaydedilmeli');
        $this->assertNotNull($yeni->civil_registry_path, 'Nüfus kaydı kaydedilmeli');
        Storage::disk(DocumentStorage::DISK)->assertExists($yeni->id_document_path);
        Storage::disk(DocumentStorage::DISK)->assertExists($yeni->civil_registry_path);
    }

    public function test_yonetici_mevcut_kisinin_eksik_belgesini_tamamlayabilir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        // Bir kişinin belgelerini sil — eksik belge durumu
        $guest = $reservation->guests()->firstOrFail();
        $guest->update(['id_document_path' => null, 'civil_registry_path' => null]);

        $payload = $this->adminPayload($reservation);
        $payload['guests'][$guest->id]['document'] = UploadedFile::fake()->image('kimlik-tamamlanan.jpg');
        $payload['guests'][$guest->id]['civil_registry'] = UploadedFile::fake()->image('nufus-tamamlanan.jpg');

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $guest->refresh();

        $this->assertNotNull($guest->id_document_path);
        $this->assertNotNull($guest->civil_registry_path);
    }

    public function test_belge_yuklemeden_de_kisi_eklenebilir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        $dortKisilik = RoomType::where('facility_id', $reservation->facility_id)->where('bed_count', 4)->firstOrFail();

        $payload = $this->adminPayload($reservation);
        $payload['room_type_id'] = $dortKisilik->id;
        $payload['guests']['yeni-1'] = [
            'full_name' => 'Belgesiz Kişi',
            'tc_no' => '12345678908',
            'birth_date' => '1993-03-03',
            'relation' => 'child',
            'customer_group_id' => $this->groupId('I'),
        ];

        // Belgeler elden alınmış olabilir; yükleme zorunlu değil
        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $reservation->fresh()->guests()->count());
    }

    /**
     * Kişi çıkarıldığında iade akışı: kayıt kendiliğinden açılır, üye
     * "iade edilecektir" ve "İade Bekleniyor" görür, iade taraflar arasında
     * yapılınca yönetici "İade edildi" ile kapatır.
     */
    public function test_kisi_cikarilinca_iade_uye_panelinde_gorunur_ve_admin_kapatir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        // Tamamı ödenmiş rezervasyon
        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation), ['admin_note' => 'Tamam']);
        $reservation->refresh();
        Payment::create([
            'reservation_id' => $reservation->id, 'kind' => 'balance', 'method' => 'bank_transfer',
            'amount' => $reservation->balanceDue(), 'status' => 'success', 'reference_no' => Payment::newReference(),
        ]);
        $reservation->refresh()->update(['status' => 'paid']);

        $odenen = $reservation->paidTotal();

        // Bir kişiyi çıkar ve ödemeyi gönder
        $payload = $this->adminPayload($reservation);
        unset($payload['surcharge_per_person_day'], $payload['adjustment_amount']);
        $ilk = array_key_first($payload['guests']);
        unset($payload['guests'][$ilk]);
        $payload['action'] = 'send_payment';
        $payload['admin_note'] = 'Talebiniz üzerine bir kişi çıkarıldı.';

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $payload)
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $beklenenIade = round($odenen - (float) $reservation->total_price, 2);

        // İade kaydı kendiliğinden açıldı
        $iade = Refund::where('reservation_id', $reservation->id)->firstOrFail();
        $this->assertSame('overpayment', $iade->reason);
        $this->assertSame('pending', $iade->status);
        $this->assertSame(number_format($beklenenIade, 2, '.', ''), $iade->amount);
        $this->assertGreaterThan(0, $beklenenIade);

        // Üye panelinde tutar ve durum görünür
        $this->actingAs($this->member)
            ->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('tutarı iade edilecektir.')
            ->assertSee('İade Bekleniyor')
            ->assertSee('Talebiniz üzerine bir kişi çıkarıldı.');

        // IBAN istenmeden yönetici iade edildi diyebilir
        $this->actingAs($admin)
            ->post(route('admin.refunds.pay', $iade))
            ->assertSessionHasNoErrors();

        $iade->refresh();
        $this->assertTrue($iade->isPaid());

        // Üye artık ödendiğini görür
        $this->actingAs($this->member)
            ->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('İade Edildi')
            ->assertDontSee('tutarı iade edilecektir.');
    }

    public function test_pesinat_iadesinde_iban_sarti_devam_eder(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();

        // Peşinatı tahsil edilmiş, sonra reddedilen başvuru
        Payment::create([
            'reservation_id' => $reservation->id, 'kind' => 'deposit', 'method' => 'bank_transfer',
            'amount' => 10000, 'status' => 'success', 'reference_no' => Payment::newReference(),
        ]);

        $this->actingAs($admin)->post(route('admin.reservations.reject', $reservation), ['admin_note' => 'Dolu']);
        $this->actingAs($this->member)->post(route('customer.refunds.request', $reservation));

        $iade = Refund::where('reservation_id', $reservation->id)->firstOrFail();

        // IBAN bildirilmeden ödendi işaretlenemez
        $this->actingAs($admin)
            ->post(route('admin.refunds.pay', $iade))
            ->assertStatus(422);
    }

    /** Yönetici düzenleme formunun mevcut hâli. */
    private function adminPayload(Reservation $reservation): array
    {
        $guests = [];

        foreach ($reservation->guests()->get() as $guest) {
            $guests[$guest->id] = [
                'id' => $guest->id,
                'full_name' => $guest->full_name,
                'tc_no' => $guest->tc_no,
                'birth_date' => $guest->birth_date->toDateString(),
                'relation' => $guest->relation,
                'customer_group_id' => $guest->customer_group_id,
            ];
        }

        return [
            'room_type_id' => $reservation->room_type_id,
            'period_id' => $reservation->period_id,
            'guests' => $guests,
            // Boş yatak alanı formda boş bırakılır; pricer kişi sayısına göre hesaplar.
            'surcharge_per_person_day' => 0,
            'adjustment_amount' => 0,
            'action' => 'save',
        ];
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Yönetici',
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => Hash::make('sifre123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'room_type_id' => $this->roomType->id,
            'period_id' => $this->period->id,
            'guests' => [
                [
                    'full_name' => 'Ahmet Yılmaz',
                    'tc_no' => '12345678901',
                    'birth_date' => '1985-04-10',
                    'relation' => 'self',
                    'customer_group_id' => $this->groupId('I'),
                    'document' => UploadedFile::fake()->image('kimlik-1.jpg'),
                    'civil_registry' => UploadedFile::fake()->image('nufus-1.jpg'),
                ],
                [
                    'full_name' => 'Fatma Yılmaz',
                    'tc_no' => '12345678902',
                    'birth_date' => '1988-09-22',
                    'relation' => 'spouse',
                    'customer_group_id' => $this->groupId('I'),
                    'document' => UploadedFile::fake()->image('kimlik-2.jpg'),
                    'civil_registry' => UploadedFile::fake()->image('nufus-2.jpg'),
                ],
            ],
            'deposit_method' => 'bank_transfer',
            'deposit_receipt' => UploadedFile::fake()->image('dekont.jpg'),
        ], $overrides);
    }

    private function createReservation(array $overrides = []): Reservation
    {
        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $this->payload($overrides))
            ->assertRedirect();

        return Reservation::latest('id')->firstOrFail();
    }

    // ---------------------------------------------------------------
    // Müracaat koşulları
    // ---------------------------------------------------------------

    /** Üyeye vadesi gelmiş, ödenmemiş bir aidat tahakkuku açar. */
    private function giveDuesDebt(User $member, int $year = 2025, float $amount = 2500): MembershipDue
    {
        return MembershipDue::create([
            'user_id' => $member->id,
            'year' => $year,
            'amount' => $amount,
            'status' => 'unpaid',
        ]);
    }

    public function test_aidat_borcu_olan_uye_basvuru_formuna_giremez(): void
    {
        $this->giveDuesDebt($this->member);

        $this->actingAs($this->member)
            ->get(route('customer.reservations.create'))
            ->assertRedirect(route('customer.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_aidat_borcu_olan_uyenin_basvurusu_reddedilir(): void
    {
        $this->giveDuesDebt($this->member);

        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Reservation::count());
    }

    public function test_odenmis_aidat_basvuruyu_engellemez(): void
    {
        $due = $this->giveDuesDebt($this->member);
        $due->update(['status' => 'paid', 'paid_at' => '2025-02-10', 'method' => 'bank_transfer']);

        $this->actingAs($this->member)
            ->get(route('customer.reservations.create'))
            ->assertOk();
    }

    public function test_gelecek_yilin_aidati_basvuruyu_engellemez(): void
    {
        // Vadesi gelmemiş tahakkuk borç sayılmaz (Madde 5/10: içinde bulunulan yıl dahil)
        $this->giveDuesDebt($this->member, year: 2027);

        $this->actingAs($this->member)
            ->get(route('customer.reservations.create'))
            ->assertOk();
    }

    public function test_uye_olmayan_misafir_aidat_kosulundan_muaftir(): void
    {
        $this->member->update(['customer_group_id' => $this->groupId('III')]);
        $this->giveDuesDebt($this->member);

        $this->actingAs($this->member)
            ->get(route('customer.reservations.create'))
            ->assertOk();
    }

    public function test_kimlik_belgesi_olmadan_basvuru_yapilamaz(): void
    {
        $payload = $this->payload();
        unset($payload['guests'][1]['document']);

        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $payload)
            ->assertSessionHasErrors('guests.1.document');

        $this->assertSame(0, Reservation::count());
    }

    public function test_oda_kapasitesini_asan_kisi_sayisi_reddedilir(): void
    {
        $payload = $this->payload();
        $payload['guests'][] = [
            'full_name' => 'Üçüncü Kişi',
            'tc_no' => '12345678903',
            'birth_date' => '1990-01-01',
            'relation' => 'child',
            'customer_group_id' => $this->groupId('I'),
            'document' => UploadedFile::fake()->image('kimlik-3.jpg'),
            'civil_registry' => UploadedFile::fake()->image('nufus-3.jpg'),
        ];

        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $payload)
            ->assertSessionHasErrors('guests');
    }

    public function test_kapali_devreye_basvuru_yapilamaz(): void
    {
        $this->period->update(['is_open' => false]);

        $this->actingAs($this->member)
            ->post(route('customer.reservations.store'), $this->payload())
            ->assertSessionHasErrors('period_id');
    }

    // ---------------------------------------------------------------
    // Başvuru oluşturma
    // ---------------------------------------------------------------

    public function test_basvuru_olusturulur_ve_ucret_tabloya_gore_hesaplanir(): void
    {
        $reservation = $this->createReservation();

        // Çolaklı 15. Devre indirimsiz (I. Grup 2.500) + 01.07 sonrası müracaat farkı 300
        $this->assertSame('pending', $reservation->status);
        $this->assertSame(6, $reservation->nights);
        $this->assertSame(2, $reservation->guests()->count());
        $this->assertEquals(2 * 2800 * 6, (float) $reservation->total_price);
        $this->assertEquals(300, (float) $reservation->surcharge_per_person_day);
        $this->assertEquals(10000, (float) $reservation->deposit_amount);
        $this->assertMatchesRegularExpression('/^2026-\d{6}$/', $reservation->code);
    }

    public function test_kimlik_belgeleri_gizli_diske_yazilir(): void
    {
        $reservation = $this->createReservation();

        foreach ($reservation->guests as $guest) {
            $this->assertNotNull($guest->id_document_path);
            $this->assertStringStartsWith('identity/', $guest->id_document_path);
            Storage::disk(DocumentStorage::DISK)->assertExists($guest->id_document_path);
        }

        $deposit = $reservation->payments()->where('kind', 'deposit')->firstOrFail();
        $this->assertSame('bank_transfer', $deposit->method);
        $this->assertSame('pending', $deposit->status);
        Storage::disk(DocumentStorage::DISK)->assertExists($deposit->receipt_path);
    }

    public function test_canli_fiyat_hesaplama_ucu_dokum_dondurur(): void
    {
        $response = $this->actingAs($this->member)->postJson(route('customer.reservations.quote'), [
            'room_type_id' => $this->roomType->id,
            'period_id' => $this->period->id,
            'guests' => [
                ['customer_group_id' => $this->groupId('I'), 'birth_date' => '1985-04-10'],
                ['customer_group_id' => $this->groupId('I'), 'birth_date' => '1988-09-22'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('total', 2 * 2800 * 6)
            ->assertJsonPath('deposit_amount', 10000)
            ->assertJsonPath('nights', 6);
    }

    // ---------------------------------------------------------------
    // Üye paneli
    // ---------------------------------------------------------------

    public function test_uye_kendi_aidat_gecmisini_gorur(): void
    {
        $this->giveDuesDebt($this->member, year: 2026, amount: 2500);
        $paid = $this->giveDuesDebt($this->member, year: 2025, amount: 2000);
        $paid->update(['status' => 'paid', 'paid_at' => '2025-02-10', 'method' => 'bank_transfer']);

        $this->actingAs($this->member)
            ->get(route('customer.dues.index'))
            ->assertOk()
            ->assertSee('Borçlu')
            ->assertSee('2026')
            ->assertSee('2025');
    }

    public function test_uye_baska_uyenin_aidatini_gormez(): void
    {
        $other = User::create([
            'name' => 'Başka Üye',
            'tc_no' => '99988877766',
            'membership_no' => 'U-9999',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => $this->groupId('I'),
            'is_active' => true,
        ]);
        MembershipDue::create(['user_id' => $other->id, 'year' => 2026, 'amount' => 7777, 'status' => 'unpaid']);

        $this->actingAs($this->member)
            ->get(route('customer.dues.index'))
            ->assertOk()
            ->assertDontSee('7.777');
    }

    public function test_uye_iletisim_bilgilerini_guncelleyebilir(): void
    {
        $this->actingAs($this->member)
            ->put(route('customer.profile.update'), [
                'phone' => '0555 000 11 22',
                'email' => 'yeni@example.com',
                'address' => 'Çankaya / Ankara',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->member->refresh();

        $this->assertSame('0555 000 11 22', $this->member->phone);
        $this->assertSame('yeni@example.com', $this->member->email);
    }

    public function test_uye_grubunu_veya_uyelik_numarasini_degistiremez(): void
    {
        $originalGroup = $this->member->customer_group_id;

        $this->actingAs($this->member)->put(route('customer.profile.update'), [
            'phone' => '0555 000 11 22',
            // Bu alanlar Dernek tarafından yönetilir; formda olmasalar da gönderilebilirler
            'customer_group_id' => $this->groupId('III'),
            'membership_no' => 'HACKED',
            'tc_no' => '00000000000',
            'is_active' => false,
            'name' => 'Değiştirilmiş Ad',
        ])->assertRedirect();

        $this->member->refresh();

        $this->assertSame($originalGroup, $this->member->customer_group_id);
        $this->assertSame('U-1042', $this->member->membership_no);
        $this->assertSame('12345678901', $this->member->tc_no);
        $this->assertSame('Ahmet Yılmaz', $this->member->name);
        $this->assertTrue($this->member->is_active);
    }

    public function test_uye_mevcut_sifresini_dogrulamadan_sifre_degistiremez(): void
    {
        $this->actingAs($this->member)
            ->put(route('customer.profile.password'), [
                'current_password' => 'yanlis-sifre',
                'password' => 'yeni-sifre-123',
                'password_confirmation' => 'yeni-sifre-123',
            ])
            ->assertSessionHasErrors('current_password', null, 'password');

        $this->assertTrue(Hash::check('sifre123', $this->member->refresh()->password));
    }

    public function test_uye_sifresini_degistirebilir(): void
    {
        $this->actingAs($this->member)
            ->put(route('customer.profile.password'), [
                'current_password' => 'sifre123',
                'password' => 'yeni-sifre-123',
                'password_confirmation' => 'yeni-sifre-123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(Hash::check('yeni-sifre-123', $this->member->refresh()->password));
    }

    public function test_basvuru_listesi_yalnizca_uyenin_kendi_basvurularini_gosterir(): void
    {
        $mine = $this->createReservation();

        $other = User::create([
            'name' => 'Başka Üye',
            'tc_no' => '99988877755',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => $this->groupId('I'),
            'is_active' => true,
        ]);
        $theirs = Reservation::create([
            'code' => '2026-999999',
            'user_id' => $other->id,
            'facility_id' => $mine->facility_id,
            'room_type_id' => $mine->room_type_id,
            'period_id' => $mine->period_id,
            'start_date' => $mine->start_date,
            'end_date' => $mine->end_date,
            'nights' => 6,
            'status' => 'pending',
            'application_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->member)
            ->get(route('customer.reservations.index'))
            ->assertOk()
            ->assertSee($mine->code)
            ->assertDontSee($theirs->code);
    }

    // ---------------------------------------------------------------
    // Belge erişimi
    // ---------------------------------------------------------------

    public function test_baskasinin_kimlik_belgesine_erisilemez(): void
    {
        $reservation = $this->createReservation();
        $guest = $reservation->guests()->firstOrFail();

        $other = User::create([
            'name' => 'Başka Üye',
            'tc_no' => '99988877766',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => $this->groupId('I'),
            'is_active' => true,
        ]);

        $this->actingAs($other)->get(route('documents.identity', $guest))->assertForbidden();
        $this->actingAs($this->member)->get(route('documents.identity', $guest))->assertOk();
        $this->actingAs($this->admin())->get(route('documents.identity', $guest))->assertOk();
    }

    public function test_kimlik_belgesi_oturumsuz_goruntulenemez(): void
    {
        $reservation = $this->createReservation();
        $guest = $reservation->guests()->firstOrFail();

        auth()->logout();

        $this->get(route('documents.identity', $guest))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------
    // Yönetici düzenlemesi ve onayı
    // ---------------------------------------------------------------

    public function test_yonetici_oda_tipini_degistirip_onaylayinca_ucret_yeniden_hesaplanir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->admin();

        $fourBed = RoomType::where('code', 'colakli-4-kisilik')->firstOrFail();
        $guests = $reservation->guests()->orderBy('sort_order')->get();

        $response = $this->actingAs($admin)->put(route('admin.reservations.update', $reservation), [
            'room_type_id' => $fourBed->id,
            'period_id' => $reservation->period_id,
            'guests' => [
                $guests[0]->id => [
                    'full_name' => $guests[0]->full_name,
                    'tc_no' => $guests[0]->tc_no,
                    'birth_date' => $guests[0]->birth_date->toDateString(),
                    'relation' => 'self',
                    'customer_group_id' => $this->groupId('I'),
                ],
                $guests[1]->id => [
                    'full_name' => $guests[1]->full_name,
                    'tc_no' => $guests[1]->tc_no,
                    'birth_date' => $guests[1]->birth_date->toDateString(),
                    'relation' => 'spouse',
                    'customer_group_id' => $this->groupId('II'),
                ],
            ],
            'empty_bed_count' => null,
            'surcharge_per_person_day' => 300,
            'adjustment_amount' => 0,
            'admin_note' => 'Talep edilen oda dolu olduğundan 4 kişilik odaya yerleştirildiniz.',
            'action' => 'approve',
        ]);

        $response->assertRedirect(route('admin.reservations.show', $reservation));

        $reservation->refresh();

        // I. Grup 2.500 + II. Grup 3.125, her ikisine de 300 TL müracaat farkı;
        // 4 kişilik odada 2 kişi kaldığı için 2 boş yatak × 300 TL × 6 gün.
        $expected = (2800 + 3425) * 6 + (2 * 300 * 6);

        $this->assertSame('approved', $reservation->status);
        $this->assertEquals($expected, (float) $reservation->total_price);
        $this->assertSame(2, $reservation->empty_bed_count);
        $this->assertSame($fourBed->id, $reservation->room_type_id);
        $this->assertSame($admin->id, $reservation->approved_by);
    }

    public function test_yonetici_listeden_kisi_cikarabilir(): void
    {
        $reservation = $this->createReservation();
        $guests = $reservation->guests()->orderBy('sort_order')->get();

        $this->actingAs($this->admin())->put(route('admin.reservations.update', $reservation), [
            'room_type_id' => $reservation->room_type_id,
            'period_id' => $reservation->period_id,
            'guests' => [
                $guests[0]->id => [
                    'full_name' => $guests[0]->full_name,
                    'tc_no' => $guests[0]->tc_no,
                    'birth_date' => $guests[0]->birth_date->toDateString(),
                    'relation' => 'self',
                    'customer_group_id' => $this->groupId('I'),
                ],
            ],
            'surcharge_per_person_day' => 300,
            'adjustment_amount' => 0,
            'action' => 'save',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $reservation->refresh();

        $this->assertSame(1, $reservation->guests()->count());
        // Tek kişi kaldığı için peşinat da tek kişi kademesine iner.
        $this->assertEquals(5000, (float) $reservation->deposit_amount);
        $this->assertEquals(2800 * 6, (float) $reservation->accommodation_total);
    }

    public function test_yonetici_elle_duzeltme_tutari_girebilir(): void
    {
        $reservation = $this->createReservation();
        $guests = $reservation->guests()->orderBy('sort_order')->get();

        $this->actingAs($this->admin())->put(route('admin.reservations.update', $reservation), [
            'room_type_id' => $reservation->room_type_id,
            'period_id' => $reservation->period_id,
            'guests' => $guests->mapWithKeys(fn ($g) => [$g->id => [
                'full_name' => $g->full_name,
                'tc_no' => $g->tc_no,
                'birth_date' => $g->birth_date->toDateString(),
                'relation' => $g->relation,
                'customer_group_id' => $g->customer_group_id,
            ]])->all(),
            'surcharge_per_person_day' => 300,
            'adjustment_amount' => -1600,
            'adjustment_note' => 'Yönetim Kurulu kararıyla indirim',
            'action' => 'save',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $reservation->refresh();

        $this->assertEquals(2 * 2800 * 6 - 1600, (float) $reservation->total_price);
    }

    public function test_yonetici_basvuruyu_gerekce_ile_reddedebilir(): void
    {
        $reservation = $this->createReservation();

        $this->actingAs($this->admin())
            ->post(route('admin.reservations.reject', $reservation), ['admin_note' => 'Kontenjan dolmuştur.'])
            ->assertRedirect();

        $reservation->refresh();

        $this->assertSame('rejected', $reservation->status);
        $this->assertSame('Kontenjan dolmuştur.', $reservation->admin_note);
    }

    public function test_musteri_admin_ekranlarina_erisemez(): void
    {
        $reservation = $this->createReservation();

        $this->actingAs($this->member)->get(route('admin.reservations.index'))->assertRedirect();
        $this->actingAs($this->member)->get(route('admin.reservations.edit', $reservation))->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Ödeme
    // ---------------------------------------------------------------

    public function test_yonetici_pesinat_dekontunu_dogrular(): void
    {
        $reservation = $this->createReservation();
        $deposit = $reservation->payments()->where('kind', 'deposit')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.payments.verify', $deposit))
            ->assertRedirect();

        $deposit->refresh();
        $reservation->refresh();

        $this->assertSame('success', $deposit->status);
        $this->assertSame('verified', $reservation->deposit_status);
        $this->assertEquals(10000, $reservation->paidTotal());
    }

    public function test_bakiye_kartla_odenince_basvuru_tamamlanir(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->admin();

        // Peşinat doğrulanır, ardından yer tahsisi yapılır.
        $deposit = $reservation->payments()->where('kind', 'deposit')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.payments.verify', $deposit));
        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation->refresh()));

        $reservation->refresh();
        $balance = $reservation->balanceDue();
        $this->assertEquals(2 * 2800 * 6 - 10000, $balance);

        // Kart ödemesi başlatılır
        $this->actingAs($this->member)
            ->post(route('customer.payment.card', $reservation), ['installment' => 1])
            ->assertOk();

        $payment = Payment::where('kind', 'balance')->latest('id')->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertEquals($balance, (float) $payment->amount);

        // Sanal POS dönüşü
        $this->post(route('payment.callback', $payment), ['decision' => 'approve'])
            ->assertRedirect(route('customer.reservations.show', $reservation));

        $payment->refresh();
        $reservation->refresh();

        $this->assertSame('success', $payment->status);
        $this->assertSame('paid', $reservation->status);
        $this->assertEquals(0.0, $reservation->balanceDue());
    }

    public function test_basarisiz_kart_odemesi_basvuruyu_tamamlamaz(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.verify', $reservation->payments()->first()));
        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation->refresh()));

        $this->actingAs($this->member)->post(route('customer.payment.card', $reservation->refresh()), ['installment' => 1]);

        $payment = Payment::where('kind', 'balance')->latest('id')->firstOrFail();

        $this->post(route('payment.callback', $payment), ['decision' => 'decline'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $payment->refresh();
        $reservation->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertSame('approved', $reservation->status);
        $this->assertGreaterThan(0, $reservation->balanceDue());
    }

    public function test_ayni_odeme_iki_kez_sonuclandirilmaz(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.verify', $reservation->payments()->first()));
        $this->actingAs($admin)->post(route('admin.reservations.approve', $reservation->refresh()));
        $this->actingAs($this->member)->post(route('customer.payment.card', $reservation->refresh()), ['installment' => 1]);

        $payment = Payment::where('kind', 'balance')->latest('id')->firstOrFail();

        $this->post(route('payment.callback', $payment), ['decision' => 'approve']);
        $this->post(route('payment.callback', $payment), ['decision' => 'approve'])->assertSessionHas('error');

        $this->assertEquals(1, Payment::where('kind', 'balance')->where('status', 'success')->count());
        $this->assertEquals((float) $reservation->refresh()->total_price, $reservation->paidTotal());
    }

    public function test_onaylanmamis_basvuru_icin_odeme_ekrani_acilmaz(): void
    {
        $reservation = $this->createReservation();

        $this->actingAs($this->member)
            ->get(route('customer.payment.show', $reservation))
            ->assertNotFound();
    }

    /** İptal edilmiş başvuru düzenlenemez. */
    public function test_iptal_edilmis_basvuru_duzenlenemez(): void
    {
        $reservation = $this->createReservation();
        $admin = $this->makeAdmin();
        $reservation->update(['status' => ReservationStatus::CANCELLED]);

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), $this->adminPayload($reservation))
            ->assertStatus(422);
    }
}
