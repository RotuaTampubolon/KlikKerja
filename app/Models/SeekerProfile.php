<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// user_id TIDAK fillable: diisi otomatis lewat relasi ($user->seekerProfile()->create(...)),
// bukan dari input form. Kalau fillable, orang bisa membuat profil atas nama user lain.
#[Fillable(['summary'])]
class SeekerProfile extends Model
{
    // Profil ini milik satu user. "belongsTo" dipakai di sisi yang MEMEGANG foreign key.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Satu profil punya banyak pengalaman kerja.
    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class);
    }
}