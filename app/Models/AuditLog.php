<?php

namespace App\Models;                                      // "alamat folder" kelas ini

use Illuminate\Database\Eloquent\Attributes\Fillable;      // daftar kolom yang BOLEH diisi massal
use Illuminate\Database\Eloquent\Model;                    // kelas dasar semua model
use Illuminate\Database\Eloquent\Relations\MorphTo;        // tipe relasi polimorfik "milik salah satu"

// Pelaku dan objek TIDAK ada di daftar ini: keduanya dipasang lewat relasi (associate)
// oleh AuditLogger, bukan diisi massal dari luar.
#[Fillable(['action', 'meta', 'ip_address'])]
class AuditLog extends Model                               // AuditLog <-> tabel "audit_logs"
{
    // Tabel ini hanya punya created_at. Mengisi null berarti "jangan kelola updated_at".
    const UPDATED_AT = null;

    // Aturan pengubahan tipe saat membaca/menulis kolom.
    protected function casts(): array
    {
        return [
            'meta' => 'array',          // kolom JSON <-> array PHP
            'created_at' => 'datetime', // objek tanggal
        ];
    }

    // Pelaku: bisa User atau Admin (atau kosong untuk sistem).
    // Laravel membaca actor_type + actor_id lalu mencari barisnya di tabel yang tepat.
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    // Objek yang dikenai tindakan (mis. Verification). Membaca subject_type + subject_id.
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}