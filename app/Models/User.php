<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SanctionType;
use App\Enums\VerificationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Kolom yang BOLEH diisi massal. Sengaja TIDAK ada:
// - password: pengguna login lewat Google
// - role: diisi eksplisit lewat kode saat memilih peran (cegah mass assignment)
// - verification_status, whatsapp_number, deletion_requested_at: diisi sistem
#[Fillable(['name', 'email', 'google_id', 'avatar_url'])]
// Disembunyikan saat model diubah ke array/JSON.
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            // String di database -> objek enum (NULL tetap NULL).
            'role' => Role::class,
            'verification_status' => VerificationStatus::class,
        ];
    }

    // Satu user punya satu profil Pencari Kerja.
    public function seekerProfile(): HasOne
    {
        return $this->hasOne(SeekerProfile::class);
    }

    // Satu user punya satu profil Pemberi Kerja.
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
    // Dipakai saat login dan oleh middleware not.blocked.
    public function currentRestriction(): ?UserSanction
    {
        // Sanksi terbaru yang bukan peringatan. Pemulihan ikut dihitung karena
        // ia membatalkan suspend/blokir sebelumnya.
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
            // Restoration: akun bebas.
            default => null,
        };
    }
}