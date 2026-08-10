<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_class_id')->constrained();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('guests')->default(1);
            $table->text('note')->nullable();
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
