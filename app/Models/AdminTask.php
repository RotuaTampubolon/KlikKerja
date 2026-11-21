<?php

namespace App\Models;                                      // "alamat folder" kelas ini

use App\Enums\AdminTaskStatus;                             // enum status tugas
use App\Enums\AdminTaskType;                               // enum jenis tugas
use Illuminate\Database\Eloquent\Attributes\Fillable;      // daftar kolom yang BOLEH diisi massal
use Illuminate\Database\Eloquent\Model;                    // kelas dasar semua model
use Illuminate\Database\Eloquent\Relations\BelongsTo;      // tipe relasi "milik satu"

// Hanya data "apa dan merujuk ke mana" yang diisi saat kartu masuk antrean.
// status, handled_by, handling_result diubah Admin lewat kode khusus, bukan massal.
#[Fillable(['type', 'ref_type', 'ref_id'])]
class AdminTask extends Model                              // AdminTask <-> tabel "admin_tasks"
{
    // Nilai awal pada objek baru: kartu baru selalu terbuka.
    protected $attributes = [
        'status' => 'open',
    ];

    // Aturan pengubahan tipe saat membaca/menulis kolom.
    protected function casts(): array
    {
        return [
            'type' => AdminTaskType::class,      // 'verification' menjadi AdminTaskType::Verification
            'status' => AdminTaskStatus::class,  // 'open' menjadi AdminTaskStatus::Open
            'handled_at' => 'datetime',          // objek tanggal
        ];
    }

    // Relasi: Admin yang menangani. Kolomnya handled_by (bukan admin_id),
    // jadi nama kolom disebut eksplisit sebagai argumen kedua.
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by');
    }
}