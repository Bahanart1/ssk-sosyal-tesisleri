<?php

namespace App\Console\Commands;

use App\Models\CustomerGroup;
use App\Support\XlsxReader;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dernek üye kütüğünü (AKTİF ÜYE LİSTESİ *.xlsx) users tablosuna aktarır.
 *
 * Beklenen sütunlar (başlık satırından ada göre eşlenir):
 *   ÜYE NO · T.C. NO · AD · SOYAD · DOĞ.TARİH · CEP TELEFON · ÜYE TARİHİ · Ç.İLİ · KURUM
 *
 * Eşleştirme anahtarı üye numarasıdır; komut yeniden çalıştırıldığında mevcut
 * üyelerin kütük alanları güncellenir, şifreleri korunur.
 */
class ImportMembers extends Command
{
    protected $signature = 'ssk:import-members
        {path : AKTİF ÜYE LİSTESİ .xlsx dosyasının yolu}
        {--group=I : Aktarılan üyelerin bağlanacağı müşteri grubu kodu}
        {--rounds= : İçe aktarmada kullanılacak bcrypt maliyeti (varsayılan: yapılandırılmış değer)}
        {--dry-run : Hiçbir kayıt yazmadan yalnızca özet ve sorunları raporlar}';

    protected $description = 'Üye listesi Excel dosyasını sisteme aktarır';

    /** Kütük başlıklarının veri anahtarlarına eşlenmesi. */
    private const COLUMNS = [
        'ÜYE NO' => 'membership_no',
        'T.C. NO' => 'tc_no',
        'AD' => 'first_name',
        'SOYAD' => 'last_name',
        'DOĞ.TARİH' => 'birth_date',
        'CEP TELEFON' => 'phone',
        'ÜYE TARİHİ' => 'joined_at',
        'Ç.İLİ' => 'city',
        'KURUM' => 'institution',
    ];

    private const BATCH = 500;

    /** @var array<string, list<string>> */
    private array $issues = [];

    public function handle(): int
    {
        $path = $this->argument('path');

        try {
            $reader = new XlsxReader($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $group = CustomerGroup::where('code', $this->option('group'))->first();

        if (! $group) {
            $this->error(sprintf('"%s" kodlu müşteri grubu bulunamadı.', $this->option('group')));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rounds = $this->option('rounds') !== null ? (int) $this->option('rounds') : null;

        $this->info(sprintf('Kaynak: %s', $path));
        $this->info(sprintf('Grup:   %s (%s)', $group->name, $group->code));

        // Kütükteki tekrarlar TC alanının tekilliğini bozacağı için, hâlihazırda
        // kullanılan numaralar baştan yüklenip aktarım boyunca genişletilir.
        $existingByMembership = DB::table('users')
            ->whereNotNull('membership_no')
            ->pluck('id', 'membership_no')
            ->all();

        $usedTcNumbers = array_flip(
            DB::table('users')->whereNotNull('tc_no')->pluck('tc_no')->all()
        );

        $now = now()->toDateTimeString();
        $inserts = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rowNumber = 0;
        $columnMap = null;

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% satır  [%bar%] %elapsed:6s%  %message%');
        $bar->setMessage('okunuyor');
        $bar->start();

        foreach ($reader->rows() as $row) {
            $rowNumber++;

            // Kütük dosyası başlık satırından önce bir kapak satırı ("AKTİF ÜYE
            // LİSTESİ") taşıyor; başlık satırı bulunana dek satırlar atlanır.
            if ($columnMap === null) {
                $columnMap = $this->resolveColumns($row);

                continue;
            }

            $record = $this->normalize($row, $columnMap, $rowNumber);

            if ($record === null) {
                $skipped++;

                continue;
            }

            // Aynı TC birden fazla üyede görünüyorsa ilk kayıt sahiplenir; sonrakiler
            // TC'siz aktarılır (kütükte kalırlar ama TC ile giriş yapamazlar).
            if ($record['tc_no'] !== null && isset($usedTcNumbers[$record['tc_no']])
                && ($existingByMembership[$record['membership_no']] ?? null) === null) {
                $this->flag('Tekrar eden TC — TC alanı boş bırakıldı', sprintf('satır %d · üye no %s · TC %s', $rowNumber, $record['membership_no'], $record['tc_no']));
                $record['tc_no'] = null;
            }

            if ($record['tc_no'] !== null) {
                $usedTcNumbers[$record['tc_no']] = true;
            }

            $existingId = $existingByMembership[$record['membership_no']] ?? null;

            if ($existingId !== null) {
                // Şifre ve rol kasıtlı olarak dokunulmadan bırakılır: yeniden içe
                // aktarma, üyenin kendi belirlediği şifreyi sıfırlamamalıdır.
                if (! $dryRun) {
                    DB::table('users')->where('id', $existingId)->update(
                        $this->attributes($record, $group->id) + ['updated_at' => $now]
                    );
                }

                $updated++;
            } else {
                $created++;

                // Şifre üretimi bilerek --dry-run dışında bırakıldı: on binlerce
                // bcrypt özeti, hiçbir şey yazmayacak bir önizlemeyi dakikalarca
                // sürdürür.
                if (! $dryRun) {
                    $inserts[] = $this->attributes($record, $group->id) + [
                        'password' => $this->passwordFor($record['tc_no'], $rounds),
                        'role' => 'customer',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($inserts) >= self::BATCH) {
                        DB::table('users')->insert($inserts);
                        $inserts = [];
                    }
                }
            }

            if ($rowNumber % 250 === 0) {
                $bar->setMessage(sprintf('%d yeni · %d güncel · %d atlandı', $created, $updated, $skipped));
                $bar->setProgress($rowNumber);
            }
        }

        if (! $dryRun && $inserts !== []) {
            DB::table('users')->insert($inserts);
        }

        $bar->setProgress($rowNumber);
        $bar->finish();
        $this->newLine(2);

        if ($columnMap === null) {
            $this->error('Başlık satırı bulunamadı — dosyada "ÜYE NO", "AD" ve "SOYAD" sütunları var mı?');

            return self::FAILURE;
        }

        $this->report($created, $updated, $skipped, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Başlık satırındaki sütun adlarını konumlarına eşler; böylece sütun sırası
     * değişen bir kütük dosyası sessizce yanlış alanlara yazmaz.
     *
     * Aktarım için zorunlu sütunları taşımayan satırlar başlık sayılmaz — dosya
     * başlıktan önce kapak satırı içerebilir.
     *
     * @param  list<string>  $row
     * @return array<string, int>|null
     */
    private function resolveColumns(array $row): ?array
    {
        $map = [];

        foreach ($row as $index => $label) {
            $label = mb_strtoupper(trim($label), 'UTF-8');

            if (isset(self::COLUMNS[$label])) {
                $map[self::COLUMNS[$label]] = $index;
            }
        }

        if (! isset($map['membership_no'], $map['first_name'], $map['last_name'])) {
            return null;
        }

        $missing = array_diff(array_values(self::COLUMNS), array_keys($map));

        if ($missing !== []) {
            $this->newLine();
            $this->warn('Bulunamayan sütunlar boş geçilecek: '.implode(', ', $missing));
        }

        return $map;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>|null
     */
    private function normalize(array $row, array $map, int $rowNumber): ?array
    {
        $value = fn (string $key) => trim($row[$map[$key] ?? -1] ?? '');

        $membershipNo = $value('membership_no');
        $name = $this->fullName($value('first_name'), $value('last_name'));

        if ($membershipNo === '' || $name === '') {
            $this->flag('Üye no veya ad boş — atlandı', sprintf('satır %d', $rowNumber));

            return null;
        }

        return [
            'membership_no' => $membershipNo,
            'name' => $name,
            'tc_no' => $this->tcNo($value('tc_no'), $membershipNo, $rowNumber),
            'phone' => $this->phone($value('phone')),
            'birth_date' => $this->date($value('birth_date')),
            'joined_at' => $this->date($value('joined_at')),
            'city' => $this->text($value('city')),
            'institution' => $this->text($value('institution')),
        ];
    }

    /** @return array<string, mixed> */
    private function attributes(array $record, int $groupId): array
    {
        return [
            'name' => $record['name'],
            'membership_no' => $record['membership_no'],
            'tc_no' => $record['tc_no'],
            'phone' => $record['phone'],
            'birth_date' => $record['birth_date'],
            'joined_at' => $record['joined_at'],
            'city' => $record['city'],
            'institution' => $record['institution'],
            'customer_group_id' => $groupId,
            'is_active' => true,
        ];
    }

    /**
     * Başlangıç şifresi üyenin TC numarasıdır. Laravel, yapılandırılmış maliyetin
     * altında üretilmiş bir özeti ilk başarılı girişte kendiliğinden yeniler.
     */
    private function passwordFor(?string $tcNo, ?int $rounds): string
    {
        $plain = $tcNo ?? Str::random(32);

        return $rounds !== null
            ? password_hash($plain, PASSWORD_BCRYPT, ['cost' => $rounds])
            : bcrypt($plain);
    }

    private function fullName(string $first, string $last): string
    {
        return trim(preg_replace('/\s+/u', ' ', $first.' '.$last));
    }

    private function tcNo(string $raw, string $membershipNo, int $rowNumber): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === '' || strlen($digits) !== 11) {
            $this->flag('Geçersiz TC — boş bırakıldı', sprintf('satır %d · üye no %s · değer "%s"', $rowNumber, $membershipNo, $raw));

            return null;
        }

        return $digits;
    }

    /** Kütükte telefonlar baştaki sıfır olmadan tutulur: 5359763558 → 05359763558. */
    private function phone(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === '') {
            return null;
        }

        $digits = ltrim($digits, '0');
        $digits = str_starts_with($digits, '90') && strlen($digits) === 12 ? substr($digits, 2) : $digits;

        return strlen($digits) === 10 ? '0'.$digits : $raw;
    }

    /** Kütükteki tarihler gg.aa.yyyy biçimindedir. */
    private function date(string $raw): ?string
    {
        if ($raw === '' || $raw === '-') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('d.m.Y', $raw);
        } catch (Throwable) {
            return null;
        }

        return $date && $date->year > 1900 && $date->year <= now()->year + 1
            ? $date->toDateString()
            : null;
    }

    private function text(string $raw): ?string
    {
        return ($raw === '' || $raw === '-') ? null : $raw;
    }

    private function flag(string $kind, string $detail): void
    {
        $this->issues[$kind][] = $detail;
    }

    private function report(int $created, int $updated, int $skipped, bool $dryRun): void
    {
        $this->table(['', 'Adet'], [
            ['Yeni kayıt', $created],
            ['Güncellenen', $updated],
            ['Atlanan', $skipped],
        ]);

        foreach ($this->issues as $kind => $details) {
            $this->warn(sprintf('%s (%d)', $kind, count($details)));

            foreach (array_slice($details, 0, 10) as $detail) {
                $this->line('  · '.$detail);
            }

            if (count($details) > 10) {
                $this->line(sprintf('  … ve %d kayıt daha', count($details) - 10));
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('--dry-run: veritabanına hiçbir şey yazılmadı.');
        }
    }
}
