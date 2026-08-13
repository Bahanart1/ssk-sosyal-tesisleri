<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');                              // "4 Kişilik Oda", "Villa"
            $table->string('code');                              // colakli-4-kisilik
            $table->enum('kind', ['room', 'villa'])->default('room');
            $table->unsignedSmallInteger('bed_count');           // yatak sayısı
            $table->boolean('is_ground_floor')->default(false);  // Çolaklı zemin kat → %10 indirim
            $table->unsignedSmallInteger('min_billed_persons')->nullable(); // Villa: 5 (Madde 8/3)
            $table->unsignedSmallInteger('max_persons')->nullable();        // Villa: 6 (ilave kişi, yataksız)

            // "Güre Tesislerinde 3 kişilik odada 2 kişi konaklaması durumunda, kalan bir
            // yatak için ücret alınmaz." (Madde 8/10) → Güre 3 kişilik oda için 2.
            $table->unsignedSmallInteger('waive_empty_bed_at_occupancy')->nullable();

            $table->unsignedSmallInteger('quantity')->default(1);           // envanterdeki adet
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['facility_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
