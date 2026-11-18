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
        Schema::table('users', function (Blueprint $table) {
        // Nullable: baru terisi setelah pengguna verifikasi WhatsApp.
        // Unique: satu nomor tidak boleh dipakai dua akun (NULL tidak dianggap kembar).
            $table->string('whatsapp_number', 20)->nullable()->unique()->after('avatar_url');

        // Default 'unverified': semua pengguna, termasuk baris lama, otomatis belum
        // terverifikasi. Diindeks karena middleware dan filter Admin sering mencarinya.
            $table->string('verification_status')->default('unverified')->index()->after('whatsapp_number');
        });
    }

    public function down(): void
    {
    Schema::table('users', function (Blueprint $table) {
        // Hapus index dulu, baru kolomnya.
        $table->dropUnique(['whatsapp_number']);
        $table->dropIndex(['verification_status']);
        $table->dropColumn(['whatsapp_number', 'verification_status']);
        });
    }
};
