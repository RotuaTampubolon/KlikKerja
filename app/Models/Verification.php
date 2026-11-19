<?php

namespace App\Models;                                      // "alamat folder" kelas ini

use App\Enums\VerificationStatus;                          // enum status milik Rotua
use Illuminate\Database\Eloquent\Attributes\Fillable;      // daftar kolom yang BOLEH diisi massal
use Illuminate\Database\Eloquent\Attributes\Hidden;        // daftar kolom yang DISEMBUNYIKAN dari JSON
use Illuminate\Database\Eloquent\Model;                    // kelas dasar semua model
use Illuminate\Database\Eloquent\Relations\BelongsTo;      // tipe relasi "milik satu"

// Hanya tiga kolom ini yang boleh diisi dari luar (mis. dari form).
// user_id, status, reviewed_by dkk sengaja TIDAK ada: itu diputuskan oleh server.
// Kalau ada, pengguna bisa mengirim request palsu berisi status=verified dan
// menyetujui dirinya sendiri. Serangan ini bernama "mass assignment".
#[Fillable(['phone_number', 'ktp_path', 'selfie_path'])]
// Path file identitas tidak boleh ikut keluar saat model diubah jadi array/JSON.
#[Hidden(['ktp_path', 'selfie_path'])]
class Verification extends Model                           // Verification <-> tabel "verifications"
{
    // Nilai awal pada objek baru, sebagai cadangan dari default di database.
    protected $attributes = [
        'status' => 'pending', // pengajuan baru selalu menunggu pemeriksaan
    ];

    // Aturan pengubahan tipe: teks di database <-> objek yang enak dipakai di PHP.
    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class, // 'pending' menjadi VerificationStatus::Pending
            'submitted_at' => 'datetime',          // teks tanggal menjadi objek Carbon
            'reviewed_at' => 'datetime',
        ];
    }

    // Relasi: pengajuan ini dimiliki satu user. Laravel mencari kolom user_id sendiri.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Admin pemeriksa. Kolomnya bernama reviewed_by (bukan admin_id),
    // jadi nama kolom disebut eksplisit sebagai argumen kedua.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}