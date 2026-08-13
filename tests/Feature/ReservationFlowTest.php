<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\MembershipDue;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use App\Services\DocumentStorage;
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
                ],
                [
                    'full_name' => 'Fatma Yılmaz',
                    'tc_no' => '12345678902',
                    'birth_date' => '1988-09-22',
                    'relation' => 'spouse',
                    'customer_group_id' => $this->groupId('I'),
                    'document' => UploadedFile::fake()->image('kimlik-2.jpg'),
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

        // Bakiye vadesi: onaydan 15 gün sonra, devre başlangıcını aşmadan
        $this->assertSame('2026-08-23', $reservation->balance_due_date->toDateString());
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
}
