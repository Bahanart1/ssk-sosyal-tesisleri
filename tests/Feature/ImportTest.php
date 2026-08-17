<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\FacilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_oda_listesi_bloklariyla_birlikte_aktarilir(): void
    {
        $this->seed(FacilitySeeder::class);

        // Gerçek dosyanın düzeni: blok başlıkları, ardından üçlü sütun grupları.
        $path = $this->xlsx([
            ['', 'MENEKŞE', '', '', 'KARANFİL', '', '', 'KARANFİL', '', '', 'NERGİS ZEMİN'],
            ['', 'BLOK', 'ODA NO', '', 'BLOK', 'ODA NO', '', 'BLOK', 'ODA NO', '', 'BLOK', 'ODA NO'],
            ['', 'MENEKŞE', '1', '5 KİŞİLİK', 'KARANFİL', '1', '4 KİŞİLİK', 'KARANFİL', '1', '4 KİŞİLİK', 'NERGİS ZEMİN', '1', 'ÇİFT KİŞİLİK'],
            ['', 'MENEKŞE', '2', '4 KİŞİLİK', 'KARANFİL', '2', '4 KİŞİLİK', 'KARANFİL', '2', '4 KİŞİLİK', 'NERGİS ZEMİN', '2', 'TEK KİŞİLİK'],
        ]);

        $this->artisan('ssk:import-rooms', ['path' => $path, '--facility' => 'colakli'])
            ->assertSuccessful();

        $colakli = Facility::where('slug', 'colakli')->sole();

        $this->assertSame(8, Room::where('facility_id', $colakli->id)->count());

        // Aynı adlı iki blok ayrı bloklar olarak adlandırılır.
        $this->assertEqualsCanonicalizing(
            ['MENEKŞE', 'KARANFİL A', 'KARANFİL B', 'NERGİS ZEMİN'],
            Room::where('facility_id', $colakli->id)->distinct()->pluck('block')->all()
        );

        // Şemada bulunmayan tipler katalogdan oluşturulur.
        $fiveBed = RoomType::where('code', 'colakli-5-kisilik')->sole();
        $this->assertSame(5, $fiveBed->bed_count);
        $this->assertSame(1, $fiveBed->quantity);

        // "TEK KİŞİLİK" bir ZEMİN bloğunda geçtiği için zemin kat tipine bağlanır.
        $groundSingle = RoomType::where('code', 'colakli-1-kisilik-zemin')->sole();
        $this->assertTrue($groundSingle->is_ground_floor);
        $this->assertSame(
            $groundSingle->id,
            Room::where('block', 'NERGİS ZEMİN')->where('number', '2')->sole()->room_type_id
        );

        // Adetler fiziksel envanterden türetilir; odası kalmayan tip pasife alınır.
        $this->assertSame(5, RoomType::where('code', 'colakli-4-kisilik')->sole()->quantity);
        $this->assertFalse(RoomType::where('code', 'colakli-1-kisilik')->sole()->is_active);
    }

    public function test_odayi_pasife_almak_oda_tipi_adedini_dusurur(): void
    {
        $this->seed(FacilitySeeder::class);

        $colakli = Facility::where('slug', 'colakli')->sole();
        $type = RoomType::where('code', 'colakli-4-kisilik')->sole();

        $room = Room::create([
            'facility_id' => $colakli->id,
            'room_type_id' => $type->id,
            'block' => 'KARDELEN',
            'number' => '7',
        ]);

        $admin = User::create([
            'name' => 'Yönetici',
            'email' => 'yonetici@example.test',
            'password' => 'gizli-sifre',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.rooms.update', $room), [
                'is_active' => 0,
                'note' => 'Klima arızası',
                'room_type_id' => $type->id,
            ])
            ->assertRedirect();

        $this->assertFalse($room->fresh()->is_active);
        $this->assertSame('Klima arızası', $room->fresh()->note);

        // Envanterdeki tek aktif oda pasife alındığı için adet sıfırlanır.
        $this->assertSame(0, $type->fresh()->quantity);
    }

    public function test_uye_listesi_kutuk_alanlariyla_aktarilir(): void
    {
        $this->seed(CustomerGroupSeeder::class);

        $path = $this->xlsx([
            ['', 'AKTİF ÜYE LİSTESİ'],
            ['ÜYE NO', 'T.C. NO', 'AD', 'SOYAD', 'DOĞ.TARİH', 'CEP TELEFON', 'ÜYE TARİHİ', 'Ç.İLİ', 'KURUM'],
            ['4772', '19981701332', 'CEVAT', 'BALCI', '15.08.1939', '5553497285', '22.01.2003', 'Ankara', 'EMEKLİ'],
            ['4832', '19981701332', 'AYŞE BEYHAN', 'MALKOÇ', '', '', '01.01.1983', '-', ''],
            ['4900', '0', 'MEHMET', 'KAYA', '', '', '', 'İzmir', 'İŞÇİ'],
        ]);

        $this->artisan('ssk:import-members', ['path' => $path])->assertSuccessful();

        $this->assertSame(3, User::where('role', 'customer')->count());

        $cevat = User::where('membership_no', '4772')->sole();
        $this->assertSame('CEVAT BALCI', $cevat->name);
        $this->assertSame('19981701332', $cevat->tc_no);
        $this->assertSame('05553497285', $cevat->phone);       // baştaki sıfır eklenir
        $this->assertSame('1939-08-15', $cevat->birth_date->toDateString());
        $this->assertSame('2003-01-22', $cevat->joined_at->toDateString());
        $this->assertSame('EMEKLİ', $cevat->institution);
        $this->assertTrue($cevat->is_active);

        // Başlangıç şifresi üyenin TC numarasıdır.
        $this->assertTrue(Hash::check('19981701332', $cevat->password));

        // Tekrar eden TC ikinci üyede boşaltılır, üye yine de kütükte kalır.
        $ayse = User::where('membership_no', '4832')->sole();
        $this->assertNull($ayse->tc_no);
        $this->assertNull($ayse->city);                         // "-" boş sayılır

        // 11 hane olmayan TC geçersizdir.
        $this->assertNull(User::where('membership_no', '4900')->sole()->tc_no);
    }

    public function test_yeniden_calistirma_uyenin_sifresini_sifirlamaz(): void
    {
        $this->seed(CustomerGroupSeeder::class);

        $rows = [
            ['ÜYE NO', 'T.C. NO', 'AD', 'SOYAD', 'DOĞ.TARİH', 'CEP TELEFON', 'ÜYE TARİHİ', 'Ç.İLİ', 'KURUM'],
            ['4772', '19981701332', 'CEVAT', 'BALCI', '', '', '', 'Ankara', 'EMEKLİ'],
        ];

        $this->artisan('ssk:import-members', ['path' => $this->xlsx($rows)])->assertSuccessful();

        $user = User::where('membership_no', '4772')->sole();
        $user->update(['password' => 'uyenin-kendi-sifresi']);

        $rows[1][8] = 'ÇALIŞMA BAKANLIĞI';

        $this->artisan('ssk:import-members', ['path' => $this->xlsx($rows)])->assertSuccessful();

        $user->refresh();

        $this->assertSame(1, User::where('role', 'customer')->count());
        $this->assertSame('ÇALIŞMA BAKANLIĞI', $user->institution);
        $this->assertTrue(Hash::check('uyenin-kendi-sifresi', $user->password));
    }

    /**
     * Satır dizisinden en küçük geçerli .xlsx dosyasını üretir. Hücreler satır içi
     * dizgi olarak yazıldığından paylaşılan dizgi tablosuna gerek kalmaz.
     *
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ssk').'.xlsx';
        $this->tempFiles[] = $path;

        $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $cells) {
            $sheet .= '<row r="'.($rowIndex + 1).'">';

            foreach ($cells as $columnIndex => $value) {
                if ($value === '') {
                    continue;
                }

                $sheet .= sprintf(
                    '<c r="%s%d" t="inlineStr"><is><t>%s</t></is></c>',
                    $this->columnLetter($columnIndex),
                    $rowIndex + 1,
                    htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                );
            }

            $sheet .= '</row>';
        }

        $sheet .= '</sheetData></worksheet>';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sayfa1" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Target="worksheets/sheet1.xml"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/>'
            .'</Relationships>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letters = chr(65 + ($i - 1) % 26).$letters;
        }

        return $letters;
    }
}
