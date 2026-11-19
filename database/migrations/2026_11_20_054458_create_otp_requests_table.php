<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = satu kali pengiriman OTP.
        // Aturan "jeda kirim ulang" dan "kuota harian" dihitung dari baris-baris ini.
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id(); // primary key

            // Peminta OTP. User dihapus -> riwayat OTP-nya ikut terhapus.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nomor tujuan OTP (sudah dinormalisasi ke format 62...).
            $table->string('phone_number', 20);

            // HASH dari kode 6 digit, BUKAN kode aslinya. Hash bcrypt panjangnya 60 karakter,
            // string() bawaan 255 karakter jadi cukup longgar.
            $table->string('code_hash');

            // Batas berlaku kode. Setelah waktu ini, kode ditolak walaupun benar.
            $table->timestamp('expires_at');

            // Jumlah kode salah yang sudah dicoba. unsignedTinyInteger = angka 0..255 tanpa minus,
            // cukup untuk batas percobaan yang kecil dan hemat ruang.
            $table->unsignedTinyInteger('attempts')->default(0);

            // Terisi saat kode benar. NULL = belum pernah berhasil dipakai.
            $table->timestamp('verified_at')->nullable();

            $table->timestamps(); // created_at dipakai untuk menghitung jeda & kuota harian

            // Mempercepat "OTP terakhir user X" dan "berapa OTP user X hari ini".
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_requests'); // kebalikan up(): hapus tabel
    }
};