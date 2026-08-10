<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'customer'])->default('customer')->after('name');
            $table->string('tc_no', 11)->nullable()->unique()->after('role');
            $table->string('phone', 20)->nullable()->after('tc_no');
            $table->foreignId('customer_class_id')->nullable()->constrained('customer_classes')->nullOnDelete()->after('phone');
            $table->boolean('is_active')->default(true)->after('customer_class_id');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_class_id');
            $table->dropColumn(['role', 'tc_no', 'phone', 'is_active']);
        });
    }
};
