<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Üye dilekçeleri. Rezervasyon dışındaki her türlü talep, itiraz ve bildirim
 * (kişi değişikliği isteği, mazeret bildirimi, şikâyet) buradan iletilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject');
            $table->string('category')->default('other');
            $table->text('body');
            $table->string('attachment_path')->nullable();

            // open → answered (yanıtlandı) / closed (işlem gerekmedi)
            $table->string('status')->default('open');
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();

            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petitions');
    }
};
