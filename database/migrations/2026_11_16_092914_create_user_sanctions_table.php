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
        Schema::create('user_sanctions', function (Blueprint $table) {
            $table->id();

            // Pengguna yang dikenai sanksi.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Admin yang menjatuhkan sanksi. Nullable + nullOnDelete: kalau akun Admin
            // dihapus, riwayat sanksi TETAP ada (admin_id jadi NULL), bukan ikut hilang.
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');                    // enum SanctionType
            $table->text('reason')->nullable();        // alasan; kewajibannya dicek di Form Request
            $table->timestamp('expires_at')->nullable(); // hanya untuk suspend: kapan berakhir

            $table->timestamps();

            // Mempercepat pertanyaan "sanksi terbaru milik user ini" (dipakai middleware not.blocked).
            $table->index(['user_id', 'created_at']);
        });
}

        public function down(): void
        {
            Schema::dropIfExists('user_sanctions');
        }
};
