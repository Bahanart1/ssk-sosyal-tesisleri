<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Bağımsız .xlsx okuyucu.
 *
 * Üye ve oda listeleri yılda birkaç kez elle içe aktarıldığı için, bu iş uğruna
 * projeye PhpSpreadsheet bağımlılığı eklemek yerine ZipArchive + SimpleXML ile
 * okunuyor. Yalnızca hücre değerleri (paylaşılan dizgiler, satır içi dizgiler ve
 * sayılar) çözümlenir; biçim, formül ve stil bilgisi göz ardı edilir.
 */
class XlsxReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var list<string> */
    private array $sharedStrings = [];

    private ZipArchive $zip;

    public function __construct(private string $path)
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Dosya okunamıyor: {$path}");
        }

        $this->zip = new ZipArchive;

        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Geçerli bir .xlsx dosyası değil: {$path}");
        }

        $this->loadSharedStrings();
    }

    public function __destruct()
    {
        @$this->zip->close();
    }

    /**
     * Sayfadaki satırları sırayla döndürür. Her satır 0 tabanlı sütun dizisidir;
     * boş hücreler '' ile doldurulur, böylece sütun konumları kaymaz.
     *
     * @return \Generator<int, list<string>>
     */
    public function rows(?string $sheetName = null): \Generator
    {
        $xml = new SimpleXMLElement($this->read($this->sheetPath($sheetName)));

        foreach ($xml->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $index = $this->columnIndex((string) $cell['r']);
                $value = $this->cellValue($cell);

                if ($value !== '') {
                    $cells[$index] = $value;
                }
            }

            if ($cells === []) {
                yield [];

                continue;
            }

            $width = max(array_keys($cells)) + 1;

            yield array_map(
                fn (int $i) => $cells[$i] ?? '',
                range(0, $width - 1)
            );
        }
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        $workbook = new SimpleXMLElement($this->read('xl/workbook.xml'));

        $names = [];

        foreach ($workbook->sheets->sheet as $sheet) {
            $names[] = (string) $sheet['name'];
        }

        return $names;
    }

    private function cellValue(SimpleXMLElement $cell): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = (int) $cell->v;

            return $this->sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            return $this->flattenText($cell->is);
        }

        return trim((string) $cell->v);
    }

    private function loadSharedStrings(): void
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }

        $xml = new SimpleXMLElement($this->read('xl/sharedStrings.xml'));

        foreach ($xml->si as $item) {
            $this->sharedStrings[] = $this->flattenText($item);
        }
    }

    /** Zengin metin hücrelerinde metin birden çok <t> parçasına bölünmüş olabilir. */
    private function flattenText(SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('m', self::NS);

        $text = '';

        foreach ($node->xpath('.//m:t') ?: [] as $part) {
            $text .= (string) $part;
        }

        return trim($text);
    }

    private function sheetPath(?string $sheetName): string
    {
        $workbook = new SimpleXMLElement($this->read('xl/workbook.xml'));
        $relationships = new SimpleXMLElement($this->read('xl/_rels/workbook.xml.rels'));

        $targets = [];

        foreach ($relationships->Relationship as $relationship) {
            $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        foreach ($workbook->sheets->sheet as $sheet) {
            if ($sheetName !== null && (string) $sheet['name'] !== $sheetName) {
                continue;
            }

            $id = (string) $sheet->attributes(self::NS_REL)->id;
            $target = $targets[$id] ?? throw new RuntimeException('Sayfa hedefi çözümlenemedi.');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
        }

        throw new RuntimeException(sprintf('Sayfa bulunamadı: %s', $sheetName ?? '(ilk sayfa)'));
    }

    /** "BC12" → 54 (0 tabanlı sütun sırası). */
    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/', $reference, $matches);

        $index = 0;

        foreach (str_split($matches[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function read(string $entry): string
    {
        $contents = $this->zip->getFromName($entry);

        if ($contents === false) {
            throw new RuntimeException("Arşivde bulunamadı: {$entry}");
        }

        return $contents;
    }
}
