<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiziksel oda envanteri.
 *
 * room_types tesisteki oda *tiplerini* ve ücretlendirmeyi taşır; bu tablo ise o
 * tiplerin blok ve oda numarasıyla tanımlı tek tek örneklerini tutar. Böylece
 * envanter room_types.quantity'deki tek sayıdan ibaret kalmaz ve ileride
 * rezervasyona somut oda ataması yapılabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();

            $table->string('block', 60);    // MENEKŞE, BEGONYA, NERGİS ZEMİN
            $table->string('number', 10);   // blok içindeki oda numarası

            // Bakım/tadilat nedeniyle geçici olarak envanterden çıkarılan odalar.
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->unique(['facility_id', 'block', 'number']);
            $table->index(['room_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
