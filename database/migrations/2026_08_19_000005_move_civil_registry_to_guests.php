<?php

use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vukuatlı nüfus kayıt örneği artık başvuru başına değil, konaklayacak her kişi
 * için ayrı ayrı isteniyor. Mevcut kayıtlarda belge başvuru sahibine (ilk kişi)
 * taşınır, böylece yüklenmiş dosyalar kaybolmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->string('civil_registry_path')->nullable()->after('id_document_path');
        });

        Reservation::query()->whereNotNull('civil_registry_path')->with('guests')->get()
            ->each(function (Reservation $reservation) {
                $ilk = $reservation->guests->sortBy('sort_order')->first();

                $ilk?->forceFill(['civil_registry_path' => $reservation->civil_registry_path])->save();
            });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('civil_registry_path');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('civil_registry_path')->nullable()->after('health_report_path');
        });

        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->dropColumn('civil_registry_path');
        });
    }
};
