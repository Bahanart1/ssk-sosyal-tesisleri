<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Üye ve aidat listeleri `where role = 'customer' order by name` ile sayfalanır.
 * On binden fazla üyede bu, her istekte tam tablo taraması ve geçici B-tree
 * sıralaması demekti; bileşik indeks hem süzmeyi hem sıralamayı karşılar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'name']);
        });
    }
};
