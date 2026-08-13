<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devre: Pazar girişle başlar, takip eden Cumartesi sona erer (Madde 7/1) → 6 gece.
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('number');       // devre no
            $table->date('start_date');                   // Pazar
            $table->date('end_date');                     // Cumartesi
            $table->unsignedSmallInteger('nights')->default(6);
            $table->boolean('is_discounted')->default(false);

            // "Birleşen Devreler" — yalnız aynı grup içindeki ardışık iki devre birleştirilebilir.
            $table->unsignedSmallInteger('combine_group')->nullable();

            $table->foreignId('room_tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();
            $table->foreignId('villa_tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();

            $table->boolean('is_open')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['facility_id', 'year', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
