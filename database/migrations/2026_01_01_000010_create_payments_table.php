<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            $table->enum('kind', ['deposit', 'balance']);
            $table->enum('method', ['card', 'bank_transfer']);
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('installment')->default(1);

            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('reference_no')->unique();

            // Havale/EFT dekontu (private diskte)
            $table->string('receipt_path')->nullable();

            // Sanal POS
            $table->string('gateway')->nullable();
            $table->string('gateway_ref')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->string('failure_reason')->nullable();

            // Havale doğrulaması admin tarafından yapılır
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
