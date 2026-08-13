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
            'dues_paid_year' => 2026,
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
            'üyeler' => ['admin.customers.index'],
            'parametreler' => ['admin.settings.index'],
        ];
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
        $this->actingAs($this->member)->get(route('customer.dashboard'))->assertOk();
        $this->actingAs($this->member)->get(route('customer.reservations.create'))->assertOk();
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
            'code' => '2026-000001',
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
