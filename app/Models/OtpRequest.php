<?php

namespace App\Models;                                      // "alamat folder" kelas ini

use Illuminate\Database\Eloquent\Attributes\Fillable;      // daftar kolom yang BOLEH diisi massal
use Illuminate\Database\Eloquent\Attributes\Hidden;        // daftar kolom yang DISEMBUNYIKAN dari JSON
use Illuminate\Database\Eloquent\Model;                    // kelas dasar semua model
use Illuminate\Database\Eloquent\Relations\BelongsTo;      // tipe relasi "milik satu"

// Tiga kolom ini diisi service OTP (Tahap 2). attempts, verified_at, dan user_id
// TIDAK fillable: dikendalikan logika server supaya tidak bisa dimanipulasi dari luar.
#[Fillable(['phone_number', 'code_hash', 'expires_at'])]
// Hash kode tidak boleh ikut bocor ke array/JSON.
#[Hidden(['code_hash'])]
class OtpRequest extends Model                             // OtpRequest <-> tabel "otp_requests"
{
    // Aturan pengubahan tipe saat membaca/menulis kolom.
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',  // objek tanggal, bisa dibandingkan dengan now()
            'verified_at' => 'datetime', // NULL sampai kode benar
            'attempts' => 'integer',     // jumlah percobaan salah sebagai angka
        ];
    }

    // Relasi: OTP ini diminta oleh satu pengguna (kolom user_id).
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}