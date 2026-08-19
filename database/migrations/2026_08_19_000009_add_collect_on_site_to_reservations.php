<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Üye bakiyeyi tesise girişte ödemeyi seçtiğinde başvuru sonlanır: artık üyeden
 * beklenen bir işlem kalmaz, kayıt kesinleşmiş rezervasyona döner ve oda
 * ataması sırasına girer. Para hâlâ tahsil edilmediği için status "paid"
 * yapılmaz; tahsilat tesiste yapılıp yönetici tarafından işlenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('collect_on_site_at')->nullable()->after('deposit_status');
            $table->index('collect_on_site_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['collect_on_site_at']);
            $table->dropColumn('collect_on_site_at');
        });
    }
};
