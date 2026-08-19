<?php

use App\Models\ReservationGuest;
use App\Models\User;
use App\Support\SearchText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aranabilir metin sütunları. Ad, TC ve üyelik numarası Türkçeye duyarlı
 * biçimde katlanmış hâlde saklanır; böylece "şahin" yazan da "ŞAHİN" kaydını
 * bulur (bkz. App\Support\SearchText).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('search_index')->nullable()->after('name')->index();
        });

        Schema::table('reservation_guests', function (Blueprint $table) {
            $table->string('search_index')->nullable()->after('full_name')->index();
        });

        User::query()->select(['id', 'name', 'tc_no', 'membership_no'])->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $user->newQuery()->whereKey($user->id)->update([
                    'search_index' => SearchText::index((string) $user->name, (string) $user->tc_no, (string) $user->membership_no),
                ]);
            }
        });

        ReservationGuest::query()->select(['id', 'full_name', 'tc_no'])->chunkById(500, function ($guests) {
            foreach ($guests as $guest) {
                $guest->newQuery()->whereKey($guest->id)->update([
                    'search_index' => SearchText::index((string) $guest->full_name, (string) $guest->tc_no),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('search_index'));
        Schema::table('reservation_guests', fn (Blueprint $table) => $table->dropColumn('search_index'));
    }
};
