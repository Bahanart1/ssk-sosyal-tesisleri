<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aidat gecikme faizi. Faiz oranı ayarlardan okunur ve borç ekranda anlık
 * hesaplanır; tahsilat yapıldığında o anki faiz bu sütuna yazılır ki oran
 * sonradan değişse bile geçmiş tahsilatlar tutarlı kalsın.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_dues', function (Blueprint $table) {
            $table->decimal('late_fee', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('membership_dues', function (Blueprint $table) {
            $table->dropColumn('late_fee');
        });
    }
};
