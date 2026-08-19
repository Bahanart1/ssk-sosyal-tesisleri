<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tesiste ödeme" yöntemi eklendiğinde payments.method sütunundaki enum kısıtı
 * güncellenmemişti; kayıt veritabanı seviyesinde reddediliyordu.
 *
 * Kısıt tümden kaldırılıp sütun düz metne çevriliyor: ödeme yöntemleri zaten
 * uygulama tarafında doğrulanıyor ve yeni bir yöntem her seferinde tablo
 * yeniden oluşturmayı gerektirmemeli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['card', 'bank_transfer'])->change();
        });
    }
};
