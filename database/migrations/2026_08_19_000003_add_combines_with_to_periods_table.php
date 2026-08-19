<?php

use App\Models\Period;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devre birleştirme artık "combine_group + ardışık numara" kuralından çıkıp
 * yöneticinin ekrandan tanımladığı açık eşleşmeye bağlanıyor: her devre,
 * kendisiyle birleşebilecek tek bir devreyi işaret eder.
 *
 * Mevcut gruplar yeni sütuna aktarılır, böylece davranış değişmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->foreignId('combines_with_id')->nullable()->after('combine_group')
                ->constrained('periods')->nullOnDelete();
        });

        Period::query()->whereNotNull('combine_group')->get()
            ->groupBy(fn (Period $p) => $p->facility_id . '-' . $p->year . '-' . $p->combine_group)
            ->each(function ($grup) {
                $sirali = $grup->sortBy('number')->values();

                foreach ($sirali as $i => $period) {
                    $sonraki = $sirali[$i + 1] ?? null;

                    if ($sonraki && $sonraki->number === $period->number + 1) {
                        $period->forceFill(['combines_with_id' => $sonraki->id])->save();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combines_with_id');
        });
    }
};
