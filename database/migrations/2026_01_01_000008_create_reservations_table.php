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
            $table->string('code')->unique();                 // 2026-000123
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('room_type_id')->constrained();

            // Bir devre veya ardışık iki devre (Madde 5/7)
            $table->foreignId('period_id')->constrained('periods');
            $table->foreignId('second_period_id')->nullable()->constrained('periods');

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('nights');           // 6 veya 13

            $table->enum('status', ['pending', 'approved', 'paid', 'rejected', 'cancelled'])
                ->default('pending');

            // Mazeret nedeniyle alt/zemin kat talebi (Madde 5/6)
            $table->boolean('ground_floor_request')->default(false);
            $table->string('ground_floor_note')->nullable();
            $table->string('health_report_path')->nullable();

            // Müracaat tarihine göre ücretlendirme kademesi
            $table->date('application_date');
            $table->decimal('surcharge_per_person_day', 10, 2)->default(0);

            // Boş yatak ücretlendirmesi (Madde 8/10)
            $table->unsignedSmallInteger('empty_bed_count')->default(0);
            $table->decimal('empty_bed_fee_per_day', 10, 2)->default(0);
            $table->decimal('empty_bed_total', 12, 2)->default(0);

            $table->decimal('accommodation_total', 12, 2)->default(0);
            $table->decimal('adjustment_amount', 12, 2)->default(0);   // admin elle düzeltme (+/-)
            $table->string('adjustment_note')->nullable();
            $table->decimal('total_price', 12, 2)->default(0);

            // Peşinat (Madde 5/8)
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->enum('deposit_status', ['pending', 'verified', 'rejected'])->default('pending');

            $table->date('balance_due_date')->nullable();     // tahsis + 15 gün (Madde 8/8)

            // Onay anındaki fiyat dökümünün tam kopyası (denetim izi)
            $table->json('price_breakdown')->nullable();

            $table->text('note')->nullable();                 // müşteri notu
            $table->text('admin_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'facility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
