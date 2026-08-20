<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oda doluluğunu normalize eder: her (başvuru, oda, devre) üçlüsü bir satır.
 *
 * Doluluk şimdiye dek reservations üzerindeki dört sütuna dağılmıştı
 * (room_id, second_room_id, period_id, second_period_id). Bu yüzden "bir oda bir
 * devrede yalnızca bir başvuruya ait olabilir" kuralı tek bir veritabanı kısıtıyla
 * ifade edilemiyordu: kısmi indeks yalnızca birincil çifti kapsıyor, bir
 * başvurunun room_id'si ile başkasının second_room_id'sinin çakışmasını
 * yakalayamıyordu.
 *
 * Satırlar ReservationObserver tarafından türetilir; elle yazılmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_period_occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->timestamps();

            // Kuralın tamamı burada: bir oda, bir devrede tek satır.
            $table->unique(['room_id', 'period_id']);
            $table->index('reservation_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('room_period_occupancies');
    }

    /** Mevcut tahsisleri yeni tabloya taşır. */
    private function backfill(): void
    {
        $statuses = "'pending', 'approved', 'paid'";

        foreach ([['room_id', 'period_id'], ['room_id', 'second_period_id'],
            ['second_room_id', 'period_id'], ['second_room_id', 'second_period_id']] as [$oda, $devre]) {
            DB::statement("
                INSERT OR IGNORE INTO room_period_occupancies (reservation_id, room_id, period_id, created_at, updated_at)
                SELECT id, {$oda}, {$devre}, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM reservations
                WHERE {$oda} IS NOT NULL AND {$devre} IS NOT NULL AND status IN ({$statuses})
            ");
        }
    }
};
