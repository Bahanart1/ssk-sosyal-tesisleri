<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Reservation;
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
 * Her ekranın hatasız açıldığını doğrular; Blade veya sorgu düzeyindeki
 * bozulmaların gözden kaçmasını engeller.
 */
class ScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $member;

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

        $this->member = User::create([
            'name' => 'Test Üye',
            'tc_no' => '10000000001',
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

    public static function adminScreens(): array
    {
        return [
            'genel bakış' => ['admin.dashboard'],
            'başvurular' => ['admin.reservations.index'],
            'ödemeler' => ['admin.payments.index'],
            'devreler' => ['admin.periods.index'],
            'tarifeler' => ['admin.tariffs.index'],
            'tesis ve odalar' => ['admin.facilities.index'],
            'oda envanteri' => ['admin.rooms.index'],
            'üyeler' => ['admin.customers.index'],
            'aidatlar' => ['admin.dues.index'],
            'parametreler' => ['admin.settings.index'],
        ];
    }

    public function test_uye_detay_sayfasi_acilir(): void
    {
        $this->makeReservation();

        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $this->member))
            ->assertOk()
            ->assertSee($this->member->name)
            ->assertSee('Aidat geçmişi');
    }

    public function test_devre_detayi_tahsis_edilen_ve_bekleyen_uyeleri_gosterir(): void
    {
        $pendingReservation = $this->makeReservation();

        $allocated = $this->makeReservation();
        $allocated->update(['status' => 'approved']);
        $allocated->guests()->update(['full_name' => 'Tahsis Edilen Kişi']);

        $period = \App\Models\Period::findOrFail($pendingReservation->period_id);

        $this->actingAs($this->admin)
            ->get(route('admin.periods.show', $period))
            ->assertOk()
            ->assertSee('Yer tahsis edilen üyeler (1)')
            ->assertSee('İnceleme bekleyen başvurular (1)')
            // Konaklayacak kişiler listesi yalnız tahsis edilenlerden oluşur
            ->assertSee('Konaklayacak kişiler (1)')
            ->assertSee('Tahsis Edilen Kişi');
    }

    public function test_devre_detayi_baska_devrenin_basvurusunu_gostermez(): void
    {
        $reservation = $this->makeReservation();
        $other = \App\Models\Period::where('number', 16)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.periods.show', $other))
            ->assertOk()
            ->assertDontSee($reservation->code);
    }

    public function test_aidat_ekrani_borclu_uyeyi_gosterir(): void
    {
        $debtor = User::customers()->get()->first(fn ($u) => $u->hasDuesDebt());

        $this->assertNotNull($debtor, 'Demo veride aidat borçlusu bir üye bulunmalı.');

        $this->actingAs($this->admin)
            ->get(route('admin.dues.index', ['status' => 'unpaid']))
            ->assertOk()
            ->assertSee($debtor->name);
    }

    /** @dataProvider adminScreens */
    public function test_yonetim_ekranlari_acilir(string $route): void
    {
        $this->actingAs($this->admin)
            ->get(route($route))
            ->assertOk();
    }

    public function test_uye_ekranlari_acilir(): void
    {
        foreach (['dashboard', 'reservations.index', 'reservations.create', 'dues.index', 'profile.edit'] as $name) {
            $this->actingAs($this->member)
                ->get(route("customer.{$name}"))
                ->assertOk();
        }
    }

    public function test_giris_ekranlari_acilir(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_basvuru_detay_ve_duzenleme_ekranlari_acilir(): void
    {
        $reservation = $this->makeReservation();

        $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reservations.edit', $reservation))->assertOk();
        $this->actingAs($this->member)->get(route('customer.reservations.show', $reservation))->assertOk();
    }

    public function test_bakiye_odeme_ekrani_acilir(): void
    {
        $reservation = $this->makeReservation();
        $reservation->update(['status' => 'approved']);

        $this->actingAs($this->member)
            ->get(route('customer.payment.show', $reservation))
            ->assertOk()
            ->assertSee('Bakiye');
    }

    private function makeReservation(): Reservation
    {
        $period = \App\Models\Period::where('number', 15)->firstOrFail();
        $roomType = \App\Models\RoomType::where('code', 'colakli-2-kisilik')->firstOrFail();

        $reservation = Reservation::create([
            'code' => '2026-' . str_pad((string) (Reservation::count() + 1), 6, '0', STR_PAD_LEFT),
            'user_id' => $this->member->id,
            'facility_id' => $roomType->facility_id,
            'room_type_id' => $roomType->id,
            'period_id' => $period->id,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'nights' => 6,
            'status' => 'pending',
            'application_date' => now()->toDateString(),
            'total_price' => 33600,
            'deposit_amount' => 10000,
        ]);

        $reservation->guests()->create([
            'full_name' => 'Test Üye',
            'tc_no' => '10000000001',
            'birth_date' => '1985-01-01',
            'relation' => 'self',
            'customer_group_id' => $this->member->customer_group_id,
            'age_category' => 'adult',
            'sort_order' => 0,
        ]);

        return $reservation->fresh();
    }
}
