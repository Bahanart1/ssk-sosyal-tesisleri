<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();      // I, II, III
            $table->string('name');                    // I. Grup
            $table->string('description')->nullable(); // Dernek Üyesi ve Bakmakla Yükümlü Olduğu Aile Fertleri
            $table->boolean('requires_membership')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
