<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\Petition;
use App\Models\User;
use App\Services\DocumentStorage;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Dilekçe akışı: üye görsel yükler, yönetici açıp yanıtlar. */
class PetitionTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DocumentStorage::DISK);
        $this->seed([SettingSeeder::class, CustomerGroupSeeder::class]);

        $this->member = User::create([
            'name' => 'Dilekçe Üyesi', 'tc_no' => '10000000077',
            'password' => Hash::make('sifre123'), 'role' => 'customer',
            'customer_group_id' => CustomerGroup::where('code', 'I')->value('id'), 'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Yönetici', 'email' => 'y@example.test',
            'password' => Hash::make('sifre123'), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    public function test_uye_dilekce_gorseli_yukleyerek_gonderir(): void
    {
        $this->actingAs($this->member)
            ->post(route('customer.petitions.store'), [
                'attachment' => UploadedFile::fake()->image('dilekce.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customer.petitions.index'));

        $petition = Petition::firstOrFail();

        $this->assertSame($this->member->id, $petition->user_id);
        $this->assertSame('open', $petition->status);
        $this->assertNotNull($petition->attachment_path);
        Storage::disk(DocumentStorage::DISK)->assertExists($petition->attachment_path);
    }

    public function test_dosyasiz_dilekce_gonderilemez(): void
    {
        $this->actingAs($this->member)
            ->post(route('customer.petitions.store'), [])
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, Petition::count());
    }

    public function test_gorseli_yalnizca_sahibi_ve_yonetici_acabilir(): void
    {
        $this->actingAs($this->member)->post(route('customer.petitions.store'), [
            'attachment' => UploadedFile::fake()->image('dilekce.jpg'),
        ]);
        $petition = Petition::firstOrFail();

        $this->actingAs($this->member)->get(route('documents.petition', $petition))->assertOk();
        $this->actingAs($this->admin)->get(route('documents.petition', $petition))->assertOk();

        $yabanci = User::create([
            'name' => 'Başka Üye', 'tc_no' => '10000000088',
            'password' => Hash::make('sifre123'), 'role' => 'customer',
            'customer_group_id' => $this->member->customer_group_id, 'is_active' => true,
        ]);

        $this->actingAs($yabanci)->get(route('documents.petition', $petition))->assertForbidden();
    }

    public function test_yonetici_gorseli_gorur_yanitlar_ve_uye_okur(): void
    {
        $this->actingAs($this->member)->post(route('customer.petitions.store'), [
            'attachment' => UploadedFile::fake()->image('dilekce.jpg'),
        ]);
        $petition = Petition::firstOrFail();

        // Yönetici listesinde görüntüleme bağlantısı vardır
        $this->actingAs($this->admin)
            ->get(route('admin.petitions.index'))
            ->assertOk()
            ->assertSee('Dilekçeyi görüntüle')
            ->assertSee(route('documents.petition', $petition), false);

        $this->actingAs($this->admin)
            ->post(route('admin.petitions.reply', $petition), [
                'reply' => 'Talebiniz uygun görülmüştür.',
                'status' => 'answered',
            ])
            ->assertSessionHasNoErrors();

        $petition->refresh();
        $this->assertSame('answered', $petition->status);
        $this->assertSame($this->admin->id, $petition->replied_by);

        $this->actingAs($this->member)
            ->get(route('customer.petitions.index'))
            ->assertOk()
            ->assertSee('Talebiniz uygun görülmüştür.')
            ->assertSee('Dilekçemi görüntüle');
    }

    public function test_ekranlar_acilir(): void
    {
        $this->actingAs($this->member)->get(route('customer.petitions.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.petitions.index'))->assertOk();
    }
}
