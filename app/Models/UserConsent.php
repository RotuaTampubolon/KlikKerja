<?php

namespace App\Models;

use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'version', 'consented_at'])]
class UserConsent extends Model
{
    // Tabel ini tidak punya created_at/updated_at, jadi matikan pengisian otomatisnya.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}