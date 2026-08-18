<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Camp2026Seeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Peşinat iadesi. Yer tahsis edilemeyen başvuru kesintisiz iade edilir;
 * üye iptalinde kırtasiye ve hizmet bedeli düşülür.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    /** Geçerli bir Türkiye IBAN'ı (mod-97 sağlaması doğru). */
    private const IBAN = 'TR33 0006 1005 1978 6457 8413 26';

    private User $admin;
    private User $member;
    private RoomType $roomType;
    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-13');

        $this->seed([
            SettingSeeder::class,
            CustomerGroupSeeder::class,
            FacilitySeeder::class,
            Camp2026Seeder::class,
            DemoUserSeeder::class,
        ]);

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->roomType = RoomType::where('code', 'colakli-2-kisilik')->firstOrFail();
        $this->period = Period::where('number', 15)->firstOrFail();

        $this->member = User::create([
            'name' => 'İade Üyesi',
            'tc_no' => '10000000055',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'),
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_red_karari_kesintisiz_iade_kaydi_acar(): void
    {
        $reservation = $this->makeReservation(paid: 10000);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.reject', $reservation), ['admin_note' => 'Kapasite doldu'])
            ->assertSessionHasNoErrors();

        $refund = Refund::where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame('rejected', $refund->reason);
        $this->assertSame('awaiting_iban', $refund->status);
        $this->assertSame('10000.00', $refund->gross_amount);
        $this->assertSame('0.00', $refund->deduction);
        $this->assertSame('10000.00', $refund->amount);
    }

    public function test_uye_iptalinde_hizmet_bedeli_dusulur(): void
    {
        Setting::put('refund.cancellation_fee', 750, 'odeme');

        $reservation = $this->makeReservation(paid: 10000);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.cancel', $reservation), ['admin_note' => 'Üye vazgeçti'])
            ->assertSessionHasNoErrors();

        $refund = Refund::where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame('cancelled', $refund->reason);
        $this->assertSame('750.00', $refund->deduction);
        $this->assertSame('9250.00', $refund->amount);
    }

    public function test_kesinti_tahsil_edilenden_fazla_olamaz(): void
    {
        Setting::put('refund.cancellation_fee', 5000, 'odeme');

        $reservation = $this->makeReservation(paid: 1200);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.cancel', $reservation), ['admin_note' => 'İptal']);

        $refund = Refund::where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame('1200.00', $refund->deduction);
        $this->assertSame('0.00', $refund->amount, 'İade eksiye düşmemeli');
    }

    public function test_odemesi_olmayan_basvuru_icin_iade_kaydi_acilmaz(): void
    {
        $reservation = $this->makeReservation(paid: 0);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.reject', $reservation), ['admin_note' => 'Peşinat yatırılmadı']);

        $this->assertSame(0, Refund::where('reservation_id', $reservation->id)->count());
    }

    public function test_uye_iban_bildirince_iade_odeme_listesine_duser(): void
    {
        $refund = $this->makeRefund();

        $this->actingAs($this->member)
            ->put(route('customer.refunds.update', $refund), [
                'iban' => self::IBAN,
                'account_holder' => 'İade Üyesi',
            ])
            ->assertSessionHasNoErrors();

        $refund->refresh();

        $this->assertSame('pending', $refund->status);
        // Boşluklar temizlenmiş hâlde saklanır
        $this->assertSame('TR330006100519786457841326', $refund->iban);
        $this->assertNotNull($refund->iban_submitted_at);
    }

    public function test_gecersiz_iban_reddedilir(): void
    {
        $refund = $this->makeRefund();

        foreach (['TR330006100519786457841327', 'TR123', 'GB33BUKB20201555555555'] as $hatali) {
            $this->actingAs($this->member)
                ->put(route('customer.refunds.update', $refund), [
                    'iban' => $hatali,
                    'account_holder' => 'İade Üyesi',
                ])
                ->assertSessionHasErrors('iban');
        }

        $this->assertSame('awaiting_iban', $refund->fresh()->status);
    }

    public function test_uye_baskasinin_iadesine_dokunamaz(): void
    {
        $refund = $this->makeRefund();

        $yabanci = User::create([
            'name' => 'Başka Üye',
            'tc_no' => '10000000099',
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => $this->member->customer_group_id,
            'is_active' => true,
        ]);

        $this->actingAs($yabanci)
            ->put(route('customer.refunds.update', $refund), [
                'iban' => self::IBAN,
                'account_holder' => 'Başka Üye',
            ])
            ->assertForbidden();
    }

    public function test_yonetici_iadeyi_odendi_isaretler(): void
    {
        $refund = $this->makeRefund();

        $this->actingAs($this->member)->put(route('customer.refunds.update', $refund), [
            'iban' => self::IBAN,
            'account_holder' => 'İade Üyesi',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.refunds.pay', $refund), ['reference_no' => 'HAVALE-123'])
            ->assertSessionHasNoErrors();

        $refund->refresh();

        $this->assertSame('paid', $refund->status);
        $this->assertSame('HAVALE-123', $refund->reference_no);
        $this->assertSame($this->admin->id, $refund->processed_by);
        $this->assertNotNull($refund->paid_at);
    }

    public function test_iban_bildirilmeden_odendi_isaretlenemez(): void
    {
        $refund = $this->makeRefund();

        $this->actingAs($this->admin)
            ->post(route('admin.refunds.pay', $refund), [])
            ->assertStatus(422);
    }

    public function test_odenmis_iade_yeniden_odenemez(): void
    {
        $refund = $this->makeRefund();

        $this->actingAs($this->member)->put(route('customer.refunds.update', $refund), [
            'iban' => self::IBAN,
            'account_holder' => 'İade Üyesi',
        ]);
        $this->actingAs($this->admin)->post(route('admin.refunds.pay', $refund), []);

        $this->actingAs($this->admin)
            ->post(route('admin.refunds.pay', $refund), [])
            ->assertStatus(422);
    }

    public function test_odenmis_iadede_hesap_guncellemesi_durumu_geri_almaz(): void
    {
        $refund = $this->makeRefund();

        $this->actingAs($this->member)->put(route('customer.refunds.update', $refund), [
            'iban' => self::IBAN,
            'account_holder' => 'İade Üyesi',
        ]);
        $this->actingAs($this->admin)->post(route('admin.refunds.pay', $refund), []);

        $this->actingAs($this->member)
            ->put(route('customer.refunds.update', $refund), [
                'iban' => self::IBAN,
                'account_holder' => 'İade Üyesi',
            ])
            ->assertStatus(422);

        $this->assertSame('paid', $refund->fresh()->status);
    }

    public function test_ikinci_kez_karara_baglamak_iadeyi_cogaltmaz(): void
    {
        $reservation = $this->makeReservation(paid: 10000);

        $this->actingAs($this->admin)->post(route('admin.reservations.reject', $reservation), ['admin_note' => 'Kapasite']);
        $this->actingAs($this->admin)->post(route('admin.reservations.cancel', $reservation), ['admin_note' => 'İptal']);

        $this->assertSame(1, Refund::where('reservation_id', $reservation->id)->count());
        $this->assertSame('rejected', Refund::where('reservation_id', $reservation->id)->value('reason'));
    }

    public function test_yonetici_iade_ekranlari_acilir(): void
    {
        $refund = $this->makeRefund();

        foreach (['pending', 'awaiting_iban', 'paid'] as $status) {
            $this->actingAs($this->admin)
                ->get(route('admin.refunds.index', ['status' => $status]))
                ->assertOk();
        }

        $this->actingAs($this->admin)
            ->get(route('admin.refunds.index', ['status' => 'awaiting_iban']))
            ->assertOk()
            ->assertSee($refund->reservation->code);
    }

    public function test_uye_detayinda_iade_bolumu_gorunur(): void
    {
        $refund = $this->makeRefund();

        $this->actingAs($this->member)
            ->get(route('customer.reservations.show', $refund->reservation))
            ->assertOk()
            ->assertSee('Peşinat iadesi')
            ->assertSee('Hesap bilgilerimi bildir');
    }

    private function makeRefund(): Refund
    {
        $reservation = $this->makeReservation(paid: 10000);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.reject', $reservation), ['admin_note' => 'Kapasite doldu']);

        auth()->logout();

        return Refund::where('reservation_id', $reservation->id)->firstOrFail();
    }

    private function makeReservation(float $paid): Reservation
    {
        $reservation = Reservation::create([
            'code' => '2026-' . str_pad((string) (Reservation::count() + 1), 6, '0', STR_PAD_LEFT),
            'user_id' => $this->member->id,
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $this->roomType->id,
            'period_id' => $this->period->id,
            'start_date' => $this->period->start_date,
            'end_date' => $this->period->end_date,
            'nights' => 6,
            'status' => 'pending',
            'deposit_status' => $paid > 0 ? 'verified' : 'pending',
            'application_date' => now()->toDateString(),
            'total_price' => 33600,
            'deposit_amount' => 10000,
        ]);

        $reservation->guests()->create([
            'full_name' => $this->member->name,
            'tc_no' => $this->member->tc_no,
            'birth_date' => '1985-01-01',
            'relation' => 'self',
            'customer_group_id' => $this->member->customer_group_id,
            'age_category' => 'adult',
            'sort_order' => 0,
        ]);

        if ($paid > 0) {
            Payment::create([
                'reservation_id' => $reservation->id,
                'kind' => 'deposit',
                'method' => 'bank_transfer',
                'amount' => $paid,
                'status' => 'success',
                'reference_no' => Payment::newReference(),
                'paid_at' => now(),
            ]);
        }

        return $reservation->fresh();
    }
}
