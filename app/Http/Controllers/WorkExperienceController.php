<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkExperienceRequest;
use App\Models\WorkExperience;
use Illuminate\Http\RedirectResponse;

class WorkExperienceController extends Controller
{
    public function store(WorkExperienceRequest $request): RedirectResponse
    {
        $profile = $request->user()->seekerProfile()->firstOrCreate();

        // Batas dari PRD. Dicek di server, bukan hanya dengan menyembunyikan tombol.
        if ($profile->workExperiences()->count() >= WorkExperience::MAX_PER_PROFILE) {
            return redirect()->route('profile.show')->withErrors([
                'experience' => 'Pengalaman kerja maksimal '.WorkExperience::MAX_PER_PROFILE.' entri.',
            ]);
        }

        // Lewat relasi: seeker_profile_id terisi otomatis dari profil MILIK user ini.
        $profile->workExperiences()->create($request->validated());

        return redirect()->route('profile.show')->with('status', 'Pengalaman kerja ditambahkan.');
    }

    // Kepemilikan sudah diperiksa middleware can:update,experience di route.
    public function update(WorkExperienceRequest $request, WorkExperience $experience): RedirectResponse
    {
        $experience->update($request->validated());

        return redirect()->route('profile.show')->with('status', 'Pengalaman kerja diperbarui.');
    }

    public function destroy(WorkExperience $experience): RedirectResponse
    {
        $experience->delete();

        return redirect()->route('profile.show')->with('status', 'Pengalaman kerja dihapus.');
    }
}