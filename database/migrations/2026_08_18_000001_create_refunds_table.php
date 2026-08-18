<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peşinat/ödeme iadeleri. Tahsilatlar payments tablosunda kaldı; iade ayrı
 * tutuluyor çünkü kendi durumu (IBAN bekleme), kesintisi ve banka referansı var
 * ve bakiye hesabına karışmamalı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();

            // rejected: yer tahsis edilemedi — faizsiz tam iade (Madde 6).
            // cancelled: üye vazgeçti — kırtasiye ve hizmet bedeli düşülür.
            $table->string('reason');

            $table->decimal('gross_amount', 10, 2);
            $table->decimal('deduction', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);

            // awaiting_iban → pending → paid (veya cancelled)
            $table->string('status')->default('awaiting_iban');

            $table->string('iban', 34)->nullable();
            $table->string('account_holder', 120)->nullable();
            $table->timestamp('iban_submitted_at')->nullable();

            $table->string('reference_no')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            // Bir başvurunun tek bir açık iadesi olur.
            $table->unique('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
