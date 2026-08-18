<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yer tahsis edilen başvuruya fiziksel oda ataması.
     *
     * Oda, başvurunun devresi boyunca doludur; aynı oda başka bir devrede
     * başkasına verilebileceğinden müsaitlik devre bazında hesaplanır.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('room_type_id')
                ->constrained('rooms')->nullOnDelete();
            $table->index(['room_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'period_id']);
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
