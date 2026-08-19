<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kalabalık aileler tek odaya sığmadığında yönetici ikinci bir oda tahsis eder.
 * Bu yalnızca yöneticinin yapabildiği bir işlemdir; üye başvuru sırasında tek
 * oda seçer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('second_room_id')->nullable()->after('room_id')
                ->constrained('rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('second_room_id');
        });
    }
};
