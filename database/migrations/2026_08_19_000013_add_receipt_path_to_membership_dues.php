<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Üye aidat borcunu panelden havale edip dekontunu yükleyebilir. Dekont
 * incelenene kadar kayıt "review" durumunda bekler; yönetici onaylayınca
 * ödenmiş sayılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_dues', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('receipt_no');
        });
    }

    public function down(): void
    {
        Schema::table('membership_dues', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
