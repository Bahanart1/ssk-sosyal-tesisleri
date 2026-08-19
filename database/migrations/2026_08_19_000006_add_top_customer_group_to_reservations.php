<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Başvurunun "en üst" müşteri grubu: konaklayacak kişiler arasındaki en yüksek
 * gruptur (I > II > III). Binlerce başvuruda gruba göre süzebilmek için her
 * fiyat hesabında bu sütuna yazılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('top_customer_group_id')->nullable()->after('room_type_id')
                ->constrained('customer_groups')->nullOnDelete();
            $table->index('top_customer_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('top_customer_group_id');
        });
    }
};
