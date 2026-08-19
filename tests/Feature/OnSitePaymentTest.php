<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Camp2026Seeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bakiyenin tesise girişte ödenmesi. Bu uç nokta veritabanı seviyesinde
 * reddediliyordu (payments.method enum kısıtı "on_site" değerini kabul
 * etmiyordu); akış uçtan uca sınanıyor.
 */
class OnSitePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-13');
        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class, FacilitySeeder::class, Camp2026Seeder::class]);

        $this->member = User::create([
            'name' => 'Tesiste Ödeyen', 'tc_no' => '10000000123',
            'password' => Hash::make('sifre123'), 'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'), 'is_active' => true,
        ]);

        $roomType = RoomType::where('code', 'colakli-2-kisilik')->firstOrFail();
        $period = Period::where('number', 15)->firstOrFail();

        $this->reservation = Reservation::create([
            'code' => '2026-000900', 'user_id' => $this->member->id,
            'facility_id' => $roomType->facility_id, 'room_type_id' => $roomType->id,
            'period_id' => $period->id, 'start_date' => $period->start_date, 'end_date' => $period->end_date,
            'nights' => 6, 'status' => 'approved', 'application_date' => '2026-08-01',
            'total_price' => 30600, 'deposit_amount' => 10000, 'deposit_status' => 'verified',
        ]);

        // Peşinat tahsil edilmiş
        Payment::create([
            'reservation_id' => $this->reservation->id, 'kind' => 'deposit', 'method' => 'bank_transfer',
            'amount' => 10000, 'status' => 'success', 'reference_no' => Payment::newReference(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_uye_bakiyeyi_tesiste_odemeyi_secince_rezervasyon_kesinlesir(): void
    {
        $this->actingAs($this->member)
            ->post(route('customer.payment.on-site', $this->reservation))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customer.reservations.show', $this->reservation));

        $payment = Payment::where('reservation_id', $this->reservation->id)
            ->where('method', 'on_site')->firstOrFail();

        $this->assertSame('balance', $payment->kind);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('20600.00', $payment->amount);

        $this->reservation->refresh();

        $this->assertNotNull($this->reservation->collect_on_site_at, 'Başvuru sonlanmalı');
        $this->assertTrue($this->reservation->collectsOnSite());
        $this->assertSame(20600.0, $this->reservation->onSiteAmount());
        $this->assertSame('Tesiste Ödeyecek', $this->reservation->statusLabel());
    }

    public function test_kesinlesen_rezervasyon_bakiye_kuyrugundan_cikip_oda_sirasina_gecer(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.reservations.index', ['stage' => 'balance']))
            ->assertOk()->assertSee($this->reservation->code);

        $this->actingAs($this->member)->post(route('customer.payment.on-site', $this->reservation));

        $this->actingAs($admin)
            ->get(route('admin.reservations.index', ['stage' => 'balance']))
            ->assertOk()->assertDontSee($this->reservation->code);

        $this->actingAs($admin)
            ->get(route('admin.reservations.index', ['stage' => 'room']))
            ->assertOk()->assertSee($this->reservation->code);
    }

    public function test_tesiste_tahsilat_listesi_tutarlari_ayri_gosterir(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($this->member)->post(route('customer.payment.on-site', $this->reservation));

        $response = $this->actingAs($admin)
            ->get(route('admin.on-site.index'))
            ->assertOk()
            ->assertSee($this->reservation->code)
            ->assertSee($this->member->name);

        $this->assertSame(20600.0, (float) $response->viewData('total'));
        $this->assertSame(1, $response->viewData('pendingCount'));
    }

    public function test_yonetici_tesiste_tahsilati_isler(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($this->member)->post(route('customer.payment.on-site', $this->reservation));

        $this->actingAs($admin)
            ->post(route('admin.on-site.collect', $this->reservation), ['note' => 'Nakit alındı'])
            ->assertSessionHasNoErrors();

        $this->reservation->refresh();

        $this->assertSame('paid', $this->reservation->status);
        $this->assertSame(0.0, $this->reservation->balanceDue());
        $this->assertSame(20600.0, $this->reservation->onSiteCollected());
        $this->assertStringContainsString('Nakit alındı', (string) $this->reservation->admin_note);

        // Tahsil edilenler sekmesine geçer
        $this->actingAs($admin)
            ->get(route('admin.on-site.index', ['durum' => 'collected']))
            ->assertOk()->assertSee($this->reservation->code);
    }

    public function test_tahsilat_iki_kez_islenemez(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($this->member)->post(route('customer.payment.on-site', $this->reservation));
        $this->actingAs($admin)->post(route('admin.on-site.collect', $this->reservation));

        $this->actingAs($admin)
            ->post(route('admin.on-site.collect', $this->reservation))
            ->assertStatus(422);

        $this->assertSame(20600.0, $this->reservation->fresh()->onSiteCollected());
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Yönetici', 'email' => 'admin-' . uniqid() . '@example.test',
            'password' => Hash::make('sifre123'), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    public function test_ayni_talep_iki_kez_kaydedilmez(): void
    {
        $this->actingAs($this->member)->post(route('customer.payment.on-site', $this->reservation));
        $this->actingAs($this->member)
            ->post(route('customer.payment.on-site', $this->reservation))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::where('reservation_id', $this->reservation->id)
            ->where('method', 'on_site')->count());
        $this->assertNotNull($this->reservation->fresh()->collect_on_site_at);
    }

    /** Ödeme kaydı varken damga eksikse üye kilitlenmemeli, kayıt onarılmalı. */
    public function test_yarim_kalmis_kayit_onarilir(): void
    {
        $this->actingAs($this->member)->post(route('customer.payment.on-site', $this->reservation));
        $this->reservation->update(['collect_on_site_at' => null]);

        $this->actingAs($this->member)
            ->post(route('customer.payment.on-site', $this->reservation))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->reservation->fresh()->collect_on_site_at);
        $this->assertSame(1, Payment::where('reservation_id', $this->reservation->id)
            ->where('method', 'on_site')->count());
    }

    public function test_baska_uye_talep_gonderemez(): void
    {
        $yabanci = User::create([
            'name' => 'Yabancı', 'tc_no' => '10000000124',
            'password' => Hash::make('sifre123'), 'role' => 'customer',
            'customer_group_id' => $this->member->customer_group_id, 'is_active' => true,
        ]);

        $this->actingAs($yabanci)
            ->post(route('customer.payment.on-site', $this->reservation))
            ->assertForbidden();
    }

    public function test_bakiyesi_olmayan_rezervasyonda_talep_alinmaz(): void
    {
        Payment::create([
            'reservation_id' => $this->reservation->id, 'kind' => 'balance', 'method' => 'card',
            'amount' => 20600, 'status' => 'success', 'reference_no' => Payment::newReference(),
        ]);

        $this->actingAs($this->member)
            ->post(route('customer.payment.on-site', $this->reservation))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::where('method', 'on_site')->count());
    }

    /**
     * Eylem adresleri yalnız form gönderimiyle çalışır; üye geri/yenile yapınca
     * "405" hata sayfası yerine ödeme ekranına dönmeli.
     */
    public function test_eylem_adresleri_tarayicidan_acilinca_yonlendirir(): void
    {
        foreach (['tesiste', 'kart', 'havale'] as $eylem) {
            $this->actingAs($this->member)
                ->get("/panel/basvuru/{$this->reservation->id}/odeme/{$eylem}")
                ->assertRedirect(route('customer.payment.show', $this->reservation));
        }

        $this->actingAs($this->member)
            ->get("/panel/basvuru/{$this->reservation->id}/iade-talebi")
            ->assertRedirect(route('customer.reservations.show', $this->reservation));
    }
}
