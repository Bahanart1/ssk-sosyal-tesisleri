<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camp_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->unique(); // Pazartesi
            $table->boolean('is_open')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camp_weeks');
    }
};
