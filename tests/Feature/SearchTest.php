<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\User;
use App\Support\SearchText;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Arama Türkçeye duyarlı olmalı: kayıtlar büyük harfle aktarıldığı için
 * "şahin" yazan da "ŞAHİN" kaydını bulabilmeli.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class]);

        $this->admin = User::create([
            'name' => 'Yönetici', 'email' => 'y@example.test',
            'password' => Hash::make('sifre123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $grup = CustomerGroup::where('code', 'I')->value('id');

        foreach ([
            ['ZEYNEP ŞAHİN', '10000000001', 'U-1'],
            ['Ayşe Yılmaz', '10000000002', 'U-2'],
            ['MEHMET ÇAĞLAR', '10000000003', 'U-3'],
            ['Ali Öztürk', '10000000004', 'U-4'],
        ] as [$ad, $tc, $no]) {
            User::create([
                'name' => $ad, 'tc_no' => $tc, 'membership_no' => $no,
                'password' => Hash::make('sifre123'), 'role' => 'customer',
                'customer_group_id' => $grup, 'is_active' => true,
            ]);
        }
    }

    public static function aramalar(): array
    {
        return [
            'küçük harfle Türkçe' => ['şahin', 'ZEYNEP ŞAHİN'],
            'büyük harfle Türkçe' => ['ŞAHİN', 'ZEYNEP ŞAHİN'],
            'Türkçesiz yazım' => ['sahin', 'ZEYNEP ŞAHİN'],
            'ı yerine i' => ['yilmaz', 'Ayşe Yılmaz'],
            'büyük harfle ı' => ['YILMAZ', 'Ayşe Yılmaz'],
            'ç ve ğ' => ['caglar', 'MEHMET ÇAĞLAR'],
            'ö ve ü' => ['ozturk', 'Ali Öztürk'],
            'ters sırada iki kelime' => ['şahin zeynep', 'ZEYNEP ŞAHİN'],
            'üyelik no' => ['U-3', 'MEHMET ÇAĞLAR'],
            'TC no' => ['10000000004', 'Ali Öztürk'],
        ];
    }

    /** @dataProvider aramalar */
    public function test_uye_aramasi_turkce_harflerden_etkilenmez(string $sorgu, string $beklenen): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['q' => $sorgu]))
            ->assertOk()
            ->assertSee($beklenen);
    }

    public function test_alakasiz_arama_sonuc_getirmez(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['q' => 'bulunmayanisim']))
            ->assertOk()
            ->assertDontSee('ZEYNEP ŞAHİN')
            ->assertDontSee('Ayşe Yılmaz');
    }

    public function test_isim_degisince_arama_indeksi_guncellenir(): void
    {
        $user = User::where('tc_no', '10000000001')->firstOrFail();
        $user->update(['name' => 'ZEYNEP KARAOĞLU']);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.index', ['q' => 'karaoglu']))
            ->assertOk()
            ->assertSee('ZEYNEP KARAOĞLU');
    }

    public function test_katlama_kurallari(): void
    {
        $this->assertSame('sahin', SearchText::fold('ŞAHİN'));
        $this->assertSame('sahin', SearchText::fold('şahin'));
        $this->assertSame('yilmaz', SearchText::fold('YILMAZ'));
        $this->assertSame('yilmaz', SearchText::fold('Yılmaz'));
        $this->assertSame('caglar ozturk', SearchText::fold('ÇAĞLAR  Öztürk'));
        $this->assertSame(['ahmet', 'yilmaz'], SearchText::tokens('  Ahmet   YILMAZ '));
        $this->assertSame([], SearchText::tokens(null));
    }
}
