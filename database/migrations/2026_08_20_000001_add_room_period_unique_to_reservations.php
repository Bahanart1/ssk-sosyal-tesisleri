<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Bir oda, bir devrede yalnızca bir başvuruya ait olabilir" kuralını veritabanı
 * seviyesinde güvence altına alır.
 *
 * Uygulama kodundaki uygunluk kontrolü ile yazma arasında kilit boşluğu vardır;
 * SQLite'ta okuma kilidi bulunmadığı için iki eşzamanlı tahsis de kontrolü geçip
 * aynı odayı yazabilir. Kısmi unique indeks bu yarışı yazma anında keser.
 *
 * Yalnızca fiilen odayı işgal eden başvuru durumları kısıtlanır; iptal veya
 * reddedilmiş başvurular odayı serbest bıraktığı için indekse dahil edilmez.
 *
 * KAPSAM: İndeks birincil (oda, devre) çiftini kapsar. Bir başvurunun room_id'si
 * ile başka bir başvurunun second_room_id'sinin çakışması, ya da ikinci devre
 * üzerinden oluşan çakışmalar tek bir kısmi indeksle ifade edilemez. Tam çözüm
 * için doluluk ayrı bir tabloya taşınmalıdır (bkz. TODO.md madde 6).
 */
return new class extends Migration
{
    private const STATUSES = "'pending', 'approved', 'paid'";

    public function up(): void
    {
        if (! $this->supportsPartialIndexes()) {
            return;
        }

        foreach ($this->indexes() as $name => $column) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON reservations (%s, period_id) WHERE %s IS NOT NULL AND status IN (%s)',
                $name,
                $column,
                $column,
                self::STATUSES
            ));
        }
    }

    public function down(): void
    {
        if (! $this->supportsPartialIndexes()) {
            return;
        }

        foreach (array_keys($this->indexes()) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    /** @return array<string, string> indeks adı → kısıtlanan sütun */
    private function indexes(): array
    {
        return [
            'reservations_room_period_unique' => 'room_id',
            'reservations_second_room_period_unique' => 'second_room_id',
        ];
    }

    /** Kısmi indeksi MySQL desteklemez; orada koruma uygulama katmanında kalır. */
    private function supportsPartialIndexes(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['sqlite', 'pgsql'], true);
    }
};
