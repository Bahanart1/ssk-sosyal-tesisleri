<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dernek üye kütüğünden (AKTİF ÜYE LİSTESİ) içe aktarılan, mevcut şemada karşılığı
 * bulunmayan alanlar: doğum tarihi, çalışma ili ve kurum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');

            // Kütükteki "Ç.İLİ" — üyenin çalıştığı/kayıtlı olduğu il.
            $table->string('city', 100)->nullable()->after('address');

            // Kütükteki "KURUM" — çalışılan kurum ya da "EMEKLİ".
            $table->string('institution')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'city', 'institution']);
        });
    }
};
