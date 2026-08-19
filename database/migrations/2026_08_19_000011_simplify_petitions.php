<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dilekçe akışı sadeleşti: üye artık form doldurmaz, dilekçesinin görselini
 * (veya PDF'ini) yükler. Konu ve metin alanları bu yüzden zorunlu olmaktan
 * çıkarılır; eski kayıtlardaki metinler okunmaya devam eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petitions', function (Blueprint $table) {
            $table->string('subject')->nullable()->change();
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('petitions', function (Blueprint $table) {
            $table->string('subject')->nullable(false)->change();
            $table->text('body')->nullable(false)->change();
        });
    }
};
