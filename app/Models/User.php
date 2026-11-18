<?php

namespace App\Models;

// Enum peran yang baru dibuat; diimpor agar bisa dipakai di casts() di bawah.
use App\Enums\Role;
use App\Enums\SanctionType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
// Authenticatable = kelas dasar untuk model yang bisa login (punya fitur
// autentikasi: id, remember token, dll).
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

// FILLABLE = daftar kolom yang BOLEH diisi sekaligus lewat User::create([...])
// atau $user->fill([...]). Kolom di luar daftar ini diabaikan (perlindungan
// terhadap mass assignment).
//
// - 'password' dikeluarkan karena pengguna login lewat Google (tanpa password).
// - 'role' SENGAJA tidak ada: kalau ada, input form seperti role=employer bisa
//   mengubah peran seseorang. Peran akan diisi eksplisit lewat kode saat
//   pengguna memilih peran (satu kali saja sesuai PRD).
// - 'deletion_requested_at' juga tidak ada: diisi oleh sistem, bukan input mentah.
#[Fillable(['name', 'email', 'google_id', 'avatar_url'])]

// HIDDEN = kolom yang disembunyikan saat model diubah ke array/JSON,
// supaya password dan token tidak bocor ke respons.
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    // HasFactory   : memungkinkan User::factory() untuk membuat data uji.
    // Notifiable   : fitur notifikasi bawaan Laravel (disiapkan, belum kita pakai).
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // CASTS = aturan konversi tipe: nilai mentah dari database diubah otomatis
    // menjadi tipe PHP yang lebih berguna saat dibaca, dan sebaliknya saat disimpan.
    protected function casts(): array
    {
        return [
            // String tanggal dari database -> objek tanggal (Carbon), jadi bisa
            // dipakai seperti $user->email_verified_at->diffForHumans().
            'email_verified_at' => 'datetime',

            // Sama: NULL berarti tidak meminta hapus akun; terisi berarti
            // sudah meminta, lengkap dengan waktunya.
            'deletion_requested_at' => 'datetime',

            // Password otomatis di-hash saat diisi, tidak pernah tersimpan
            // sebagai teks asli. (Dipertahankan dari bawaan Laravel.)
            'password' => 'hashed',

            // String 'seeker'/'employer' di database -> objek enum Role.
            // Nilai NULL tetap NULL (belum memilih peran). Mengisi nilai di luar
            // enum, misalnya 'admin', akan menimbulkan error ValueError.
            'role' => Role::class,
        ];
    }

    // Sisi lawan dari belongsTo: satu user punya satu profil Pencari Kerja.
    // "hasOne" dipakai di sisi yang TIDAK memegang foreign key.
    public function seekerProfile(): HasOne
        {
            return $this->hasOne(SeekerProfile::class);
        }

    // Satu user punya satu profil Pemberi Kerja (kalau perannya employer).
    public function employerProfile(): HasOne
    {
        return $this->hasOne(EmployerProfile::class);
    }

        // Riwayat persetujuan syarat dan privasi.
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    // Riwayat sanksi dari Admin.
    public function sanctions(): HasMany
    {
        return $this->hasMany(UserSanction::class);
    }
    
    // Mengembalikan sanksi yang SEDANG membatasi akun, atau null kalau akun bebas.
    // Dipakai saat login (sekarang) dan oleh middleware not.blocked (Tahap 3).
    public function currentRestriction(): ?UserSanction
    {
        // Ambil sanksi terbaru yang bukan peringatan. Pemulihan ikut dihitung
        // karena ia membatalkan suspend/blokir sebelumnya.
        // latest('id'): urut dari id terbesar; lebih andal daripada created_at
        // kalau dua sanksi dibuat dalam detik yang sama.
        $latest = $this->sanctions()
            ->whereIn('type', [
                SanctionType::Suspension->value,
                SanctionType::Ban->value,
                SanctionType::Restoration->value,
            ])
            ->latest('id')
            ->first();

        if ($latest === null) {
            return null;
        }

        return match ($latest->type) {
            SanctionType::Ban => $latest,
            // Suspend tanpa batas waktu, atau batasnya belum lewat: masih berlaku.
            SanctionType::Suspension => ($latest->expires_at === null || $latest->expires_at->isFuture())
                ? $latest
                : null,
            // Restoration (dan sisanya): akun bebas.
            default => null,
        };
    }
}