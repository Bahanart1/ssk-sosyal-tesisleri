<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Camp2026Seeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Devre ayarları: birleşme eşleşmeleri ve devre başına tarife. */
class PeriodSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-13');
        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class, FacilitySeeder::class, Camp2026Seeder::class, DemoUserSeeder::class]);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ayar_ekrani_acilir(): void
    {
        $this->actingAs($this->admin)->get(route('admin.periods.settings'))->assertOk();
    }

    public function test_yonetici_birlesmeyi_degistirebilir(): void
    {
        $on5 = Period::where('number', 15)->firstOrFail();
        $on7 = Period::where('facility_id', $on5->facility_id)->where('number', 17)->firstOrFail();

        // Başlangıçta 15 → 16
        $this->assertSame(
            Period::where('facility_id', $on5->facility_id)->where('number', 16)->value('id'),
            $on5->combines_with_id
        );

        $this->actingAs($this->admin)
            ->put(route('admin.periods.settings.save'), [
                'periods' => [
                    $on5->id => [
                        'combines_with_id' => $on7->id,
                        'room_tariff_id' => $on5->room_tariff_id,
                        'villa_tariff_id' => $on5->villa_tariff_id,
                        'is_open' => 1,
                        'is_discounted' => 0,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($on7->id, $on5->fresh()->combines_with_id);
        $this->assertTrue($on5->fresh()->canCombineWith($on7));
    }

    public function test_birlesme_kaldirilabilir(): void
    {
        $on5 = Period::where('number', 15)->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.periods.settings.save'), [
                'periods' => [
                    $on5->id => [
                        'combines_with_id' => '',
                        'room_tariff_id' => $on5->room_tariff_id,
                        'is_open' => 1,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($on5->fresh()->combines_with_id);
    }

    public function test_baska_tesisin_devresiyle_birlestirilemez(): void
    {
        $colakli = Period::where('number', 15)->firstOrFail();
        $gure = Period::where('facility_id', '!=', $colakli->facility_id)->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.periods.settings.save'), [
                'periods' => [
                    $colakli->id => [
                        'combines_with_id' => $gure->id,
                        'room_tariff_id' => $colakli->room_tariff_id,
                        'is_open' => 1,
                    ],
                ],
            ])
            ->assertSessionHasErrors('periods');

        $this->assertNotSame($gure->id, $colakli->fresh()->combines_with_id);
    }

    public function test_devre_tarifesi_degistirilebilir(): void
    {
        $period = Period::where('number', 15)->firstOrFail();
        $baskaTarife = \App\Models\Tariff::where('facility_id', $period->facility_id)
            ->where('scope', 'room')->where('id', '!=', $period->room_tariff_id)->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.periods.settings.save'), [
                'periods' => [
                    $period->id => [
                        'combines_with_id' => $period->combines_with_id,
                        'room_tariff_id' => $baskaTarife->id,
                        'is_open' => 1,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($baskaTarife->id, $period->fresh()->room_tariff_id);
    }
}
