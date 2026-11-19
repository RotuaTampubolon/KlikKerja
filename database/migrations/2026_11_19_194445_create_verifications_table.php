<?php

use Illuminate\Database\Migrations\Migration;   // kelas dasar semua migration
use Illuminate\Database\Schema\Blueprint;       // "kertas gambar" untuk mendefinisikan kolom
use Illuminate\Support\Facades\Schema;          // alat untuk membuat/menghapus tabel

// Migration "anonim": tidak perlu nama kelas, Laravel mengenalinya dari nama file.
return new class extends Migration
{
    // up() = apa yang dilakukan saat `php artisan migrate`.
    public function up(): void
    {
        // Buat tabel baru bernama "verifications". $table adalah kertas gambarnya.
        Schema::create('verifications', function (Blueprint $table) {
            $table->id(); // kolom id: angka besar, otomatis bertambah, jadi primary key

            // Pemilik pengajuan. foreignId = kolom angka yang menunjuk ke baris di tabel lain.
            // constrained() = otomatis menunjuk ke tabel "users" (ditebak dari nama user_id).
            // cascadeOnDelete() = kalau user dihapus, pengajuannya ikut terhapus.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nomor WhatsApp, format 62xxxxxxxxxx. string(20) = teks maksimal 20 karakter.
            $table->string('phone_number', 20);

            // Yang disimpan adalah LOKASI file (path), bukan isi gambarnya.
            $table->string('ktp_path');    // foto KTP
            $table->string('selfie_path'); // selfie memegang KTP

            // Status pengajuan: pending / verified / rejected (nilai enum VerificationStatus).
            // default('pending') = kalau tidak diisi, otomatis 'pending'.
            // index() = "daftar isi" agar pencarian berdasarkan status cepat (dipakai antrean Admin).
            $table->string('status')->default('pending')->index();

            // Alasan penolakan. text = teks panjang. nullable = boleh kosong (kosong kalau tidak ditolak).
            $table->text('rejected_reason')->nullable();

            // Waktu pengguna mengirim dokumen. useCurrent() = diisi waktu sekarang secara otomatis.
            $table->timestamp('submitted_at')->useCurrent();

            // Admin pemeriksa. Tabelnya "admins", bukan "users", jadi harus ditulis eksplisit.
            // nullOnDelete() = kalau Admin dihapus, riwayat tetap ada dan kolom ini jadi NULL.
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();

            // Waktu Admin memutuskan. Kosong selama masih menunggu.
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps(); // otomatis membuat created_at dan updated_at

            // Indeks gabungan untuk pertanyaan "pengajuan user X yang berstatus Y".
            $table->index(['user_id', 'status']);
        });
    }

    // down() = kebalikan up(), dijalankan saat `php artisan migrate:rollback`.
    public function down(): void
    {
        Schema::dropIfExists('verifications'); // hapus tabel kalau ada
    }
};