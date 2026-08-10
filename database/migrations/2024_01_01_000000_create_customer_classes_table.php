<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Class 1, Class 2, Class 3
            $table->string('description')->nullable();
            $table->decimal('daily_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_classes');
    }
};
