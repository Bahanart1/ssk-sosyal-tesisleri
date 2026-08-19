<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vukuatlı nüfus kayıt örneği. Kimlik belgesi kişi başına alınırken bu belge
 * başvuru başına birdir: üyenin kaydı, birlikte konaklayacak yakınlarının
 * hepsini listelediği için müşteri grubu (I./II. Grup) tespitine esas olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('civil_registry_path')->nullable()->after('health_report_path');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('civil_registry_path');
        });
    }
};
