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
            $table->string('membership_no', 20)->nullable()->unique()->after('role');
            $table->string('tc_no', 11)->nullable()->unique()->after('membership_no');
            $table->string('phone', 20)->nullable()->after('tc_no');
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete()->after('phone');

            // Aidat borcu kontrolü (Usul ve Esaslar Madde 5/10): borcu olan üyenin formu işleme alınmaz.
            $table->unsignedSmallInteger('dues_paid_year')->nullable()->after('customer_group_id');

            $table->boolean('is_active')->default(true)->after('dues_paid_year');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
            $table->dropColumn(['role', 'membership_no', 'tc_no', 'phone', 'dues_paid_year', 'is_active']);
        });
    }
};
