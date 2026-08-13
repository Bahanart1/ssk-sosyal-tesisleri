<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarife = aynı fiyata tabi devrelerin oluşturduğu bant.
     * Örn. "Çolaklı 1 ve 3. Devreler (İndirimli)".
     *
     * scope=room  → Tablo 1 (oda ücretleri)
     * scope=villa → Tablo 2 (Çolaklı villaları)
     */
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->enum('scope', ['room', 'villa'])->default('room');
            $table->boolean('is_discounted')->default(false);

            // null = "Alınmaz" (indirimli devrelerde boş yatak ücreti alınmaz - Madde 8/9)
            $table->decimal('empty_bed_fee', 10, 2)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
    }
};
