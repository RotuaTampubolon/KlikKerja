<?php

use Illuminate\Database\Migrations\Migration;   // kelas dasar semua migration
use Illuminate\Database\Schema\Blueprint;       // "kertas gambar" untuk mendefinisikan kolom
use Illuminate\Support\Facades\Schema;          // alat untuk membuat/menghapus tabel

return new class extends Migration
{
    public function up(): void
    {
        // Antrean kerja Admin. Modul lain (laporan, tiket) akan memasukkan pekerjaan
        // lewat AdminTaskQueue::push() yang kita buat di tahap berikutnya.
        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id(); // primary key

            // Jenis pekerjaan, diisi nilai enum AdminTaskType: verification/report/ticket.
            $table->string('type');

            // Rujukan POLIMORFIK ke data asal: ref_type = jenis data, ref_id = id barisnya.
            // Sengaja BUKAN foreign key (alasannya ada di materi di atas).
            $table->string('ref_type', 50);
            $table->unsignedBigInteger('ref_id'); // angka besar tanpa minus, sama tipe dengan id()

            // Status pekerjaan, diisi nilai enum AdminTaskStatus. Tugas baru selalu 'open'.
            $table->string('status')->default('open');

            // Admin yang menangani. Kalau Admin dihapus, tugasnya TETAP ada dan kolom ini jadi NULL.
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();

            // Catatan hasil penanganan (mis. "disetujui", "ditolak: KTP buram").
            $table->text('handling_result')->nullable();

            // Kapan selesai ditangani. Kosong selama belum selesai.
            $table->timestamp('handled_at')->nullable();

            $table->timestamps(); // created_at = kapan kartu masuk antrean

            // Dasbor Admin sering menghitung "antrean terbuka per jenis".
            $table->index(['status', 'type']);
            // Mencari kartu dari data asalnya (mis. kartu milik verifikasi #12).
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_tasks'); // kebalikan up(): hapus tabel
    }
};