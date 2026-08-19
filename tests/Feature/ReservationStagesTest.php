<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
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
 * Başvurular ekranındaki iş akışı sekmeleri. Her başvuru tam olarak bir
 * aşamada görünmeli; aksi hâlde yönetici aynı kaydı iki listede görür.
 */
class ReservationStagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, Reservation> */
    private function herAsamadanBirer(): array
    {
        $room = Room::create([
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $this->roomType->id,
            'block' => 'A',
            'number' => '1',
            'is_active' => true,
        ]);

        $kayitlar = [
            'deposit' => ['status' => 'pending', 'deposit_status' => 'pending'],
            'review' => ['status' => 'pending', 'deposit_status' => 'verified'],
            'balance' => ['status' => 'approved', 'deposit_status' => 'verified'],
            'room' => ['status' => 'paid', 'deposit_status' => 'verified'],
            'done' => ['status' => 'paid', 'deposit_status' => 'verified', 'room_id' => $room->id],
            'closed' => ['status' => 'rejected', 'deposit_status' => 'rejected'],
        ];

        $sonuc = [];

        foreach ($kayitlar as $asama => $nitelikler) {
            $sonuc[$asama] = tap($this->makeReservation())->update($nitelikler);
        }

        return $sonuc;
    }

    public function test_her_asama_yalnizca_kendi_basvurusunu_listeler(): void
    {
        $basvurular = $this->herAsamadanBirer();

        foreach ($basvurular as $asama => $beklenen) {
            $response = $this->actingAs($this->admin)
                ->get(route('admin.reservations.index', ['stage' => $asama]))
                ->assertOk()
                ->assertSee($beklenen->code);

            foreach ($basvurular as $digerAsama => $diger) {
                if ($digerAsama !== $asama) {
                    $response->assertDontSee($diger->code);
                }
            }
        }
    }

    public function test_asama_sayilari_toplami_tum_basvurulara_esittir(): void
    {
        $this->herAsamadanBirer();

        $counts = $this->actingAs($this->admin)
            ->get(route('admin.reservations.index'))
            ->assertOk()
            ->viewData('stageCounts');

        foreach (['deposit', 'review', 'balance', 'room', 'done', 'closed'] as $asama) {
            $this->assertSame(1, $counts[$asama], "{$asama} aşamasında bir başvuru olmalı");
        }

        $this->assertSame(Reservation::count(), array_sum($counts), 'Hiçbir başvuru aşamalar dışında kalmamalı');
    }

    public function test_reddedilen_pesinat_hala_pesinat_asamasindadir(): void
    {
        $reddedilen = $this->makeReservation();
        $reddedilen->update(['status' => 'pending', 'deposit_status' => 'rejected']);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['stage' => 'deposit']))
            ->assertOk()
            ->assertSee($reddedilen->code);
    }

    public function test_oda_ataninca_basvuru_tamamlandi_asamasina_gecer(): void
    {
        $room = Room::create([
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $this->roomType->id,
            'block' => 'B', 'number' => '2', 'is_active' => true,
        ]);

        $reservation = $this->makeReservation();
        $reservation->update(['status' => 'paid']);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['stage' => 'room']))
            ->assertOk()->assertSee($reservation->code);

        $this->actingAs($this->admin)
            ->post(route('admin.reservations.assign-room', $reservation), ['room_id' => $room->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['stage' => 'room']))
            ->assertOk()->assertDontSee($reservation->code);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['stage' => 'done']))
            ->assertOk()->assertSee($reservation->code);
    }

    public function test_gecersiz_asama_tum_listeyi_gosterir(): void
    {
        $reservation = $this->makeReservation();

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['stage' => 'olmayan']))
            ->assertOk()
            ->assertSee($reservation->code);
    }

    public function test_asama_ile_devre_suzgeci_birlikte_calisir(): void
    {
        $on6 = Period::where('number', 16)->firstOrFail();

        $bu = $this->makeReservation();
        $bu->update(['status' => 'paid']);

        $digerDevre = $this->makeReservation();
        $digerDevre->update(['status' => 'paid', 'period_id' => $on6->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['stage' => 'room', 'period' => $this->period->id]))
            ->assertOk()
            ->assertSee($bu->code)
            ->assertDontSee($digerDevre->code);
    }

    public function test_basvuru_en_yuksek_gruba_gore_siniflanir(): void
    {
        $I = \App\Models\CustomerGroup::where('code', 'I')->firstOrFail();
        $II = \App\Models\CustomerGroup::where('code', 'II')->firstOrFail();
        $III = \App\Models\CustomerGroup::where('code', 'III')->firstOrFail();

        // Karışık gruplu başvuru: I. Grup en üsttür
        $karisik = $this->makeReservation();
        $karisik->guests()->update(['customer_group_id' => $III->id]);
        $karisik->guests()->create([
            'full_name' => 'Üye Kendisi', 'tc_no' => '11111111111', 'birth_date' => '1980-01-01',
            'relation' => 'self', 'customer_group_id' => $I->id, 'age_category' => 'adult', 'sort_order' => 1,
        ]);

        // Yalnız II. ve III. gruptan oluşan başvuru
        $ikinci = $this->makeReservation();
        $ikinci->guests()->update(['customer_group_id' => $II->id]);

        app(\App\Services\ReservationService::class)->applyBreakdown(
            $karisik->fresh(), app(\App\Services\ReservationService::class)->repriceExisting($karisik->fresh())
        );
        app(\App\Services\ReservationService::class)->applyBreakdown(
            $ikinci->fresh(), app(\App\Services\ReservationService::class)->repriceExisting($ikinci->fresh())
        );

        $this->assertSame($I->id, $karisik->fresh()->top_customer_group_id, 'I. Grup varsa başvuru I. Gruptur');
        $this->assertSame($II->id, $ikinci->fresh()->top_customer_group_id);

        // Süzgeç
        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['group' => $I->id]))
            ->assertOk()
            ->assertSee($karisik->code)
            ->assertDontSee($ikinci->code);

        $this->actingAs($this->admin)
            ->get(route('admin.reservations.index', ['group' => $II->id]))
            ->assertOk()
            ->assertSee($ikinci->code)
            ->assertDontSee($karisik->code);
    }

    private function makeReservation(): Reservation
    {
        $user = User::create([
            'name' => 'Üye ' . (User::count() + 1),
            'tc_no' => str_pad((string) (10000000000 + User::count()), 11, '0', STR_PAD_LEFT),
            'password' => Hash::make('sifre123'),
            'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'),
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'code' => '2026-' . str_pad((string) (Reservation::count() + 1), 6, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'facility_id' => $this->roomType->facility_id,
            'room_type_id' => $this->roomType->id,
            'period_id' => $this->period->id,
            'start_date' => $this->period->start_date,
            'end_date' => $this->period->end_date,
            'nights' => 6,
            'status' => 'pending',
            'application_date' => now()->toDateString(),
            'total_price' => 33600,
            'deposit_amount' => 10000,
        ]);

        $reservation->guests()->create([
            'full_name' => $user->name,
            'tc_no' => $user->tc_no,
            'birth_date' => '1985-01-01',
            'relation' => 'self',
            'customer_group_id' => $user->customer_group_id,
            'age_category' => 'adult',
            'sort_order' => 0,
        ]);

        return $reservation->fresh();
    }
}
