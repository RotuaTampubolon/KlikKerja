<?php

use Illuminate\Database\Migrations\Migration;   // kelas dasar semua migration
use Illuminate\Database\Schema\Blueprint;       // "kertas gambar" untuk mendefinisikan kolom
use Illuminate\Support\Facades\Schema;          // alat untuk membuat/menghapus tabel

return new class extends Migration
{
    public function up(): void
    {
        // Catatan jejak: siapa melakukan apa, kapan, terhadap apa (PRD 12.1).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); // primary key

            // PELAKU: bisa User, Admin, atau sistem (NULL). nullableMorphs membuat dua kolom
            // sekaligus -- actor_type (nama kelas, mis. App\Models\Admin) dan actor_id --
            // beserta indeksnya. Dipakai morph karena pelakunya berasal dari DUA tabel berbeda.
            $table->nullableMorphs('actor');

            // TINDAKAN, ditulis konsisten "objek.aksi", mis. verification.approved.
            // index() = mempercepat pencarian "semua tindakan jenis X".
            $table->string('action')->index();

            // OBJEK yang dikenai tindakan (mis. Verification #7). Juga polimorfik, juga boleh kosong.
            $table->nullableMorphs('subject');

            // Data tambahan yang fleksibel (mis. alasan penolakan) dalam format JSON.
            $table->json('meta')->nullable();

            // Alamat IP pelaku. 45 karakter cukup untuk IPv6 terpanjang.
            $table->string('ip_address', 45)->nullable();

            // HANYA created_at. Log tidak boleh diubah, jadi tidak ada updated_at.
            // useCurrent() = database mengisi waktu sekarang secara otomatis.
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs'); // kebalikan up(): hapus tabel
    }
};