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
        // Schema::create = membuat tabel BARU (bukan mengubah yang sudah ada).
        Schema::create('admins', function (Blueprint $table) {
            $table->id();                          // primary key auto-increment
            $table->string('name');
            $table->string('email')->unique();     // dipakai untuk login, tidak boleh kembar
            $table->string('password');            // wajib: Admin login dengan password

            // Secret TOTP (rahasia yang dibagi ke aplikasi authenticator).
            // Nullable: Admin baru belum mengaktifkan 2FA.
            // Tipe text, bukan string: nilai terenkripsi lebih panjang dari 255 karakter.
            $table->text('two_factor_secret')->nullable();

            $table->timestamps();                  // created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       // Membalik up(): hapus tabel kalau ada.
        Schema::dropIfExists('admins');
    }
};
