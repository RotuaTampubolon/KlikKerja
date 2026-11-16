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
    Schema::create('work_experiences', function (Blueprint $table) {
        $table->id();

        // Satu profil punya BANYAK pengalaman, jadi TIDAK unique (beda dengan user_id di atas).
        // 'seeker_profile_id' otomatis dipetakan ke tabel seeker_profiles.
        $table->foreignId('seeker_profile_id')->constrained()->cascadeOnDelete();

        $table->string('title');                    // contoh: "Pelayan warung"
        $table->string('workplace')->nullable();    // tempat bekerja
        $table->date('started_at')->nullable();
        $table->date('ended_at')->nullable();       // NULL = masih bekerja di sana
        $table->text('description')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_experiences');
    }
};
