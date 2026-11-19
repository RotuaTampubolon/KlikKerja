<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkExperience;

class WorkExperiencePolicy
{
    // Hanya pemilik profil yang boleh mengubah pengalaman kerjanya.
    // Dibandingkan lewat ID profil, tanpa memuat relasi tambahan.
    public function update(User $user, WorkExperience $experience): bool
    {
        return $user->seekerProfile?->id === $experience->seeker_profile_id;
    }

    // Aturan hapus sama dengan aturan ubah.
    public function delete(User $user, WorkExperience $experience): bool
    {
        return $this->update($user, $experience);
    }
}