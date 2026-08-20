<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\RoomInventory;
use App\Support\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fiziksel oda envanterini (ODALAR *.xlsx) rooms tablosuna aktarır.
 *
 * Dosya, her bloğun yan yana üç sütunla (BLOK · ODA NO · tip) verildiği bir ızgara
 * düzenindedir. Blok grupları "BLOK" başlıklarının bulunduğu sütunlardan
 * saptanır, böylece blok sayısı ya da sırası değişse de okuma bozulmaz.
 *
 * Oda tipi, yatak sayısı ve bloğun zemin katta olup olmamasıyla belirlenir;
 * "NERGİS ZEMİN" bloğundaki "ÇİFT KİŞİLİK" bir oda, iki yataklı zemin kat oda
 * tipine bağlanır (zemin kat odalarında %10 indirim uygulanır).
 */
class ImportRooms extends Command
{
    protected $signature = 'ssk:import-rooms
        {path : ODALAR .xlsx dosyasının yolu}
        {--facility=colakli : Odaların ait olduğu tesisin slug değeri}
        {--sheet= : Okunacak sayfa adı (varsayılan: ilk sayfa)}
        {--prune : Dosyada bulunmayan mevcut odaları siler}
        {--keep-empty-types : Hiç odası kalmayan oda tiplerini pasife almaz}
        {--dry-run : Hiçbir kayıt yazmadan yalnızca özet ve sorunları raporlar}';

    protected $description = 'Oda listesi Excel dosyasını sisteme aktarır';

    /** Yatak sayısı olarak çözümlenen oda tipi etiketleri. */
    private const BED_LABELS = [
        'TEK KİŞİLİK' => 1,
        'ÇİFT KİŞİLİK' => 2,
    ];

    /**
     * Kütükte geçen ama şemada henüz tanımlı olmayan oda tipleri için tanımlar.
     * Anahtar: "<tesis slug>:<yatak>:<zemin kat mı>".
     */
    private const TYPE_CATALOG = [
        'colakli:5:0' => [
            'code' => 'colakli-5-kisilik',
            'name' => '5 Kişilik Oda',
            'description' => 'Beş yataklı aile odası.',
        ],
        'colakli:1:1' => [
            'code' => 'colakli-1-kisilik-zemin',
            'name' => '1 Kişilik Oda (Zemin Kat)',
            'description' => 'Zemin katta tek yataklı oda. Ortopedik engel, yaşlılık veya sağlık mazereti olanlar için; kişi başı günlük ücrette %10 indirim uygulanır.',
        ],
    ];

    /** @var array<string, list<string>> */
    private array $issues = [];

    public function __construct(private readonly RoomInventory $inventory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $facility = Facility::where('slug', $this->option('facility'))->first();

        if (! $facility) {
            $this->error(sprintf('"%s" slug değerine sahip tesis bulunamadı.', $this->option('facility')));

            return self::FAILURE;
        }

        try {
            $reader = new XlsxReader($this->argument('path'));
            $rooms = $this->parse($reader, $this->option('sheet') ?: null);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rooms === []) {
            $this->error('Dosyada oda bulunamadı — "BLOK" başlıklı bir satır var mı?');

            return self::FAILURE;
        }

        $this->info(sprintf('Kaynak: %s', $this->argument('path')));
        $this->info(sprintf('Tesis:  %s', $facility->name));
        $this->newLine();

        $dryRun = (bool) $this->option('dry-run');

        $types = $this->resolveTypes($facility, $rooms, $dryRun);

        if ($types === null) {
            return self::FAILURE;
        }

        $this->summarize($rooms);

        if ($dryRun) {
            $this->newLine();
            $this->reportIssues();
            $this->comment('--dry-run: veritabanına hiçbir şey yazılmadı.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($facility, $rooms, $types) {
            $keep = [];

            foreach ($rooms as $room) {
                $model = Room::updateOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'block' => $room['block'],
                        'number' => $room['number'],
                    ],
                    [
                        'room_type_id' => $types[$this->typeKey($facility, $room)],
                        'is_active' => true,
                    ]
                );

                $keep[] = $model->id;
            }

            if ($this->option('prune')) {
                $pruned = Room::where('facility_id', $facility->id)->whereNotIn('id', $keep)->delete();

                if ($pruned > 0) {
                    $this->warn(sprintf('%d oda dosyada bulunmadığı için silindi.', $pruned));
                }
            }

            $this->syncQuantities($facility);
        });

        $this->newLine();
        $this->reportIssues();
        $this->info(sprintf('%d oda aktarıldı.', count($rooms)));

        return self::SUCCESS;
    }

    /**
     * Izgarayı düz bir oda listesine çevirir.
     *
     * @return list<array{block: string, number: string, label: string, beds: int, ground: bool}>
     */
    private function parse(XlsxReader $reader, ?string $sheet): array
    {
        $groups = null;
        $raw = [];

        foreach ($reader->rows($sheet) as $row) {
            if ($groups === null) {
                $found = array_keys($row, 'BLOK', true);

                if ($found !== []) {
                    $groups = $found;
                }

                continue;
            }

            foreach ($groups as $column) {
                $block = trim($row[$column] ?? '');
                $number = trim($row[$column + 1] ?? '');
                $label = trim($row[$column + 2] ?? '');

                if ($block === '' || $number === '') {
                    continue;
                }

                $raw[] = ['group' => $column, 'block' => $block, 'number' => $number, 'label' => $label];
            }
        }

        return $this->finalize($this->disambiguate($raw));
    }

    /**
     * Aynı blok adı birden çok sütun grubunda geçebiliyor (dosyada iki ayrı
     * KARANFİL bloğu var). Bunlar ayrı bloklar olduğu için grup sırasına göre
     * "KARANFİL A", "KARANFİL B" biçiminde adlandırılır.
     *
     * @param  list<array{group: int, block: string, number: string, label: string}>  $raw
     * @return list<array{group: int, block: string, number: string, label: string}>
     */
    private function disambiguate(array $raw): array
    {
        $groupsByBlock = [];

        foreach ($raw as $room) {
            $groupsByBlock[$room['block']][$room['group']] = true;
        }

        $suffixes = [];

        foreach ($groupsByBlock as $block => $groups) {
            if (count($groups) < 2) {
                continue;
            }

            foreach (array_keys($groups) as $index => $group) {
                $suffixes[$block][$group] = $block.' '.chr(65 + $index);
            }

            $this->flag('Aynı adlı blok birden çok kez geçiyor — ayrı bloklar olarak adlandırıldı', sprintf('%s → %s', $block, implode(', ', $suffixes[$block])));
        }

        foreach ($raw as $i => $room) {
            $raw[$i]['block'] = $suffixes[$room['block']][$room['group']] ?? $room['block'];
        }

        return $raw;
    }

    /**
     * Oda tipi etiketlerini yatak sayısına çevirir, tekrar eden oda numaralarını eler.
     *
     * @param  list<array{group: int, block: string, number: string, label: string}>  $raw
     * @return list<array{block: string, number: string, label: string, beds: int, ground: bool}>
     */
    private function finalize(array $raw): array
    {
        $rooms = [];
        $seen = [];

        foreach ($raw as $room) {
            $beds = $this->beds($room['label']);

            if ($beds === null) {
                $this->flag('Çözümlenemeyen oda tipi — atlandı', sprintf('%s %s · "%s"', $room['block'], $room['number'], $room['label']));

                continue;
            }

            $key = $room['block'].'#'.$room['number'];

            if (isset($seen[$key])) {
                $this->flag('Tekrar eden oda — atlandı', $key);

                continue;
            }

            $seen[$key] = true;

            $rooms[] = [
                'block' => $room['block'],
                'number' => $room['number'],
                'label' => $room['label'],
                'beds' => $beds,
                'ground' => str_contains(mb_strtoupper($room['block'], 'UTF-8'), 'ZEMİN'),
            ];
        }

        return $rooms;
    }

    private function beds(string $label): ?int
    {
        $label = mb_strtoupper(trim($label), 'UTF-8');

        if (isset(self::BED_LABELS[$label])) {
            return self::BED_LABELS[$label];
        }

        return preg_match('/^(\d+)\s*KİŞİLİK$/u', $label, $matches) ? (int) $matches[1] : null;
    }

    /**
     * Her (yatak sayısı, zemin kat) bileşimini bir oda tipine bağlar; şemada
     * bulunmayan bileşimleri tanım kataloğundan oluşturur.
     *
     * @param  list<array{beds: int, ground: bool}>  $rooms
     * @return array<string, int>|null Tip anahtarı → room_type id
     */
    private function resolveTypes(Facility $facility, array $rooms, bool $dryRun): ?array
    {
        $existing = RoomType::where('facility_id', $facility->id)
            ->where('kind', 'room')
            ->get()
            ->keyBy(fn (RoomType $type) => sprintf('%s:%d:%d', $facility->slug, $type->bed_count, (int) $type->is_ground_floor));

        $resolved = [];
        $unknown = [];

        foreach ($rooms as $room) {
            $key = $this->typeKey($facility, $room);

            if (isset($resolved[$key])) {
                continue;
            }

            if ($existing->has($key)) {
                $resolved[$key] = $existing[$key]->id;

                continue;
            }

            $definition = self::TYPE_CATALOG[$key] ?? null;

            if ($definition === null) {
                $unknown[$key] = sprintf('%d yataklı%s oda ("%s")', $room['beds'], $room['ground'] ? ' zemin kat' : '', $room['label']);

                continue;
            }

            $this->line(sprintf('  + yeni oda tipi: %s', $definition['name']));

            if ($dryRun) {
                $resolved[$key] = 0;

                continue;
            }

            $resolved[$key] = RoomType::create($definition + [
                'facility_id' => $facility->id,
                'kind' => 'room',
                'bed_count' => $room['beds'],
                'is_ground_floor' => $room['ground'],
                'quantity' => 0,
                'is_active' => true,
                // Mevcut tiplerin sıraları 1..5; yenileri onların ardına yerleşsin.
                'sort_order' => 10 + $room['beds'],
            ])->id;
        }

        if ($unknown !== []) {
            $this->error('Şemada karşılığı olmayan ve katalogda tanımı bulunmayan oda tipleri var:');

            foreach ($unknown as $description) {
                $this->line('  · '.$description);
            }

            $this->line('Bunlar için ImportRooms::TYPE_CATALOG içine tanım eklenmeli.');

            return null;
        }

        return $resolved;
    }

    /** @param array{beds: int, ground: bool} $room */
    private function typeKey(Facility $facility, array $room): string
    {
        return sprintf('%s:%d:%d', $facility->slug, $room['beds'], (int) $room['ground']);
    }

    /**
     * Adetleri fiziksel envanterden yeniden hesaplar. Kural, oda envanteri
     * ekranıyla ortak olduğu için RoomInventory servisinde tutulur.
     */
    private function syncQuantities(Facility $facility): void
    {
        $pasifeAlinan = $this->inventory->sync($facility, ! $this->option('keep-empty-types'));

        foreach ($pasifeAlinan as $ad) {
            $this->warn(sprintf('"%s" için envanterde hiç oda yok — oda tipi pasife alındı.', $ad));
        }
    }

    /** @param list<array{block: string, beds: int, ground: bool}> $rooms */
    private function summarize(array $rooms): void
    {
        $byBlock = [];

        foreach ($rooms as $room) {
            $byBlock[$room['block']][$room['label']] = ($byBlock[$room['block']][$room['label']] ?? 0) + 1;
        }

        $table = [];

        foreach ($byBlock as $block => $labels) {
            $detail = [];

            foreach ($labels as $label => $count) {
                $detail[] = sprintf('%d× %s', $count, $label);
            }

            $table[] = [$block, array_sum($labels), implode(' · ', $detail)];
        }

        $table[] = ['TOPLAM', count($rooms), ''];

        $this->table(['Blok', 'Oda', 'Dağılım'], $table);
    }

    private function flag(string $kind, string $detail): void
    {
        $this->issues[$kind][] = $detail;
    }

    private function reportIssues(): void
    {
        foreach ($this->issues as $kind => $details) {
            $this->warn(sprintf('%s (%d)', $kind, count($details)));

            foreach ($details as $detail) {
                $this->line('  · '.$detail);
            }
        }
    }
}
