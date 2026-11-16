<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('user_consents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('type');     // 'terms' atau 'privacy' (enum ConsentType)
        $table->string('version');  // versi dokumen yang disetujui, contoh: "2026-11-01"

        // Waktu persetujuan. Ini log, jadi tidak memakai created_at/updated_at.
        $table->timestamp('consented_at');

        // Unique gabungan (composite): satu user tidak boleh dicatat dua kali
        // menyetujui dokumen yang SAMA pada versi yang SAMA.
        // Versi baru dokumen = baris baru, itu yang kita inginkan.
        $table->unique(['user_id', 'type', 'version']);
    });
}

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
