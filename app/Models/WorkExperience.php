<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'workplace', 'started_at', 'ended_at', 'description'])]
class WorkExperience extends Model
{
    // Batas: maksimal 10 entri pengalaman per profil.
    public const MAX_PER_PROFILE = 10;

    protected function casts(): array
    {
        return [
            // String tanggal dari database -> objek tanggal.
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function seekerProfile(): BelongsTo
    {
        return $this->belongsTo(SeekerProfile::class);
    }
}