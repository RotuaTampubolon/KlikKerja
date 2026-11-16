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
}