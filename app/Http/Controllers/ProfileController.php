<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSeekerProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        // firstOrCreate pada relasi: ambil profil milik user ini, atau buat kalau belum ada
        // (user_id terisi otomatis dari relasi). Tidak ada ID dari URL yang dipercaya.
        $profile = $user->seekerProfile()->firstOrCreate();

        return view('profil.pencari', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    public function update(UpdateSeekerProfileRequest $request): RedirectResponse
    {
        $profile = $request->user()->seekerProfile()->firstOrCreate();

        // validated() hanya berisi kolom yang lolos aturan (di sini: summary),
        // jadi input lain seperti user_id tidak pernah masuk.
        $profile->update($request->validated());

        return redirect()->route('profile.show')->with('status', 'Profil berhasil diperbarui.');
    }
}