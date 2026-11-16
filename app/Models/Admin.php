<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
// Authenticatable: kelas dasar untuk model yang bisa login.
// Admin memakainya juga, tapi nanti lewat "guard" terpisah dari User (Tahap 6).
use Illuminate\Foundation\Auth\User as Authenticatable;

// Kolom yang boleh diisi massal. two_factor_secret TIDAK ada di sini:
// diisi lewat kode khusus saat Admin mengaktifkan 2FA, bukan dari input form.
#[Fillable(['name', 'email', 'password'])]

// Disembunyikan dari array/JSON agar password dan secret tidak bocor.
#[Hidden(['password', 'two_factor_secret'])]
class Admin extends Authenticatable
{
    protected function casts(): array
    {
        return [
            // Password di-hash otomatis saat diisi.
            'password' => 'hashed',

            // Secret dienkripsi otomatis saat disimpan dan didekripsi saat dibaca.
            // Kalau database bocor, secret TOTP tidak terbaca langsung.
            'two_factor_secret' => 'encrypted',
        ];
    }
}