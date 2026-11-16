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
    Schema::create('seeker_profiles', function (Blueprint $table) {
        $table->id();

        // Foreign key ke users.id.
        // - unique()        : satu user hanya boleh punya SATU profil (one-to-one).
        // - constrained()   : database menjaga agar user_id selalu menunjuk user yang ada.
        //                     Nama tabel ditebak dari nama kolom: user_id -> users.
        // - cascadeOnDelete(): kalau user dihapus, profilnya ikut terhapus.
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

        // Ringkasan maks. 300 karakter (PRD). Nullable: profil baru belum ada ringkasan.
        // Batas ini juga akan dicek di Form Request; batas di database adalah jaring pengaman.
        $table->string('summary', 300)->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seeker_profiles');
    }
};
