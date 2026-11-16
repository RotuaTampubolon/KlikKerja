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
    Schema::create('employer_profiles', function (Blueprint $table) {
        $table->id();

        // Satu user = satu profil Pemberi Kerja (unique), dan ikut terhapus bila user dihapus.
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

        // Perorangan atau usaha. Wajib: ditentukan saat profil dibuat.
        $table->string('account_type');

        // "Nama atau nama usaha" (PRD): satu kolom untuk dua kemungkinan isi.
        $table->string('display_name');

        // Wilayah. Sengaja BUKAN foreign key dulu karena tabel `regions` milik Anggota 3.
        // unsignedBigInteger = tipe yang sama dengan id() agar nanti bisa dijadikan FK.
        // index() = mempercepat pencarian/filter berdasarkan wilayah.
        // Nullable di database; kewajiban mengisinya dicek di Form Request nanti.
        $table->unsignedBigInteger('region_id')->nullable()->index();

        // Informasi tambahan (bebas, panjang). Boleh kosong.
        $table->text('additional_info')->nullable();

        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('employer_profiles');
    }
};
