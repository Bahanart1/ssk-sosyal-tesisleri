<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Üyelik aidatı defteri: her üye için yıl yıl tahakkuk ve tahsilat kaydı.
     *
     * "İçinde bulunulan yıl dahil önceki yıllara ait aidat borcu bulunan üyelerin
     *  tahakkuk eden borçları ödenmediği sürece müracaat formları işleme alınmaz."
     *  (Usul ve Esaslar, Madde 5/10)
     */
    public function up(): void
    {
        Schema::create('membership_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 10, 2)->default(0);

            $table->enum('status', ['unpaid', 'paid', 'waived'])->default('unpaid');
            $table->date('paid_at')->nullable();
            $table->enum('method', ['cash', 'bank_transfer', 'card'])->nullable();
            $table->string('receipt_no')->nullable();
            $table->string('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year']);
            $table->index(['year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_dues');
    }
};
