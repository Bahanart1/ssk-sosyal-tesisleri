<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            $table->string('full_name');
            $table->string('tc_no', 11);
            $table->date('birth_date');

            $table->enum('relation', [
                'self', 'spouse', 'child', 'parent',
                'bride', 'groom', 'grandchild', 'guest',
            ])->default('guest');

            // Her kişi kendi grubuna göre ücretlendirilir (Tablo 1 / Tablo 2 sütunları)
            $table->foreignId('customer_group_id')->constrained();

            // Devre başlangıcına göre hesaplanır (Madde 8/7)
            $table->enum('age_category', ['adult', 'child_6_11', 'child_0_5'])->default('adult');
            $table->boolean('wants_meal')->default(false); // yalnız 0-5 yaş için anlamlı

            // Geçerli kimlik belgesi zorunlu (Madde 5/3) — private diskte saklanır.
            // Müşteri başvurusunda zorunludur; adminin sonradan eklediği kişilerde
            // belge daha sonra yüklenebileceği için kolon nullable bırakılmıştır.
            $table->string('id_document_path')->nullable();

            $table->decimal('unit_price', 10, 2)->default(0);  // kişi başı günlük (snapshot)
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_guests');
    }
};
