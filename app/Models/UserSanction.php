<?php

namespace App\Models;

use App\Enums\SanctionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id dan admin_id TIDAK fillable: keduanya ditentukan oleh server
// (user dari relasi, admin dari Admin yang sedang login), bukan dari input form.
#[Fillable(['type', 'reason', 'expires_at'])]
class UserSanction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SanctionType::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    // Pesan untuk pengguna yang dibatasi. Alasan internal dari Admin tidak ditampilkan.
    public function blockedMessage(): string
    {
        if ($this->type === SanctionType::Ban) {
            return 'Akun Anda diblokir. Hubungi Admin untuk informasi lebih lanjut.';
        }

        if ($this->expires_at === null) {
            return 'Akun Anda sedang ditangguhkan.';
        }

        $until = $this->expires_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H.i');

        return "Akun Anda ditangguhkan sampai {$until} WIB.";
    }
}