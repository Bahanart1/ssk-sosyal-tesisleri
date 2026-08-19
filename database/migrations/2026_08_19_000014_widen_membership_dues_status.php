<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * status kolonu enum CHECK kısıtıyla tanımlıydı; üyenin yüklediği dekontun
 * incelemede beklediği "review" durumu için kolon serbest metne çevrilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_dues', function (Blueprint $table) {
            $table->string('status')->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        // Enum kısıtına geri dönüş veri kaybettirebilir; string bırakılır.
    }
};
