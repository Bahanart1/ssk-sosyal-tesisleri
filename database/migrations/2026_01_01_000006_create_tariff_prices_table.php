<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();

            // Kişi başı günlük ücret (vergiler dahil)
            $table->decimal('adult_price', 10, 2);

            // Tablo 2'de 6-12 yaş ücreti açıkça verilir; Tablo 1'de yoktur (adult × %60 hesaplanır).
            $table->decimal('child_price', 10, 2)->nullable();

            // Villa "En düşük Günlük Ücret" tabanı (Madde 8/3)
            $table->decimal('min_daily_total', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['tariff_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_prices');
    }
};
