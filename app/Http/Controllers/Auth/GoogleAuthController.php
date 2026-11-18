<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;
use App\Enums\SanctionType;
use App\Models\UserSanction;

class GoogleAuthController extends Controller
{
    // Langkah 1: kirim pengguna ke halaman login Google.
    // Socialite membuat URL Google lengkap (client_id, redirect_uri, scope)
    // dan menyimpan "state" (token acak) di sesi untuk mencegah pemalsuan callback.
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    // Langkah 2: Google mengembalikan pengguna ke sini membawa "code".
    public function callback(Request $request): RedirectResponse
    {
        try {
            // Socialite memeriksa state, menukar code dengan data pengguna (lewat
            // client_secret), lalu mengembalikan objek berisi id, nama, email, foto.
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Contoh penyebab: pengguna menekan "Batal" di Google, atau state tidak cocok.
            report($e); // dicatat ke storage/logs/laravel.log untuk debugging
            return redirect()->route('login')
                ->withErrors(['google' => 'Masuk dengan Google gagal. Silakan coba lagi.']);
        }

        // Cari berdasarkan google_id. Kalau belum ada, buat user baru.
        // Parameter ke-2 hanya dipakai SAAT MEMBUAT, jadi login berikutnya
        // tidak menimpa nama/foto yang mungkin sudah diganti di Pengaturan.
        $user = User::firstOrCreate(
            ['google_id' => $google->getId()],
            [
                'name' => $google->getName(),
                'email' => $google->getEmail(),
                'avatar_url' => $google->getAvatar(),
            ],
        );

        // PRD: akun yang diblokir atau masih disuspend tidak boleh masuk.
        // Cek SEBELUM Auth::login, supaya tidak pernah tercipta sesi login.
        $restriction = $user->currentRestriction();
        if ($restriction !== null) {
            return redirect()->route('login')
                ->withErrors(['google' => $this->restrictionMessage($restriction)]);
        }

        Auth::login($user);
        // Ganti ID sesi setelah login (mencegah session fixation).
        $request->session()->regenerate();

        // Pengguna yang belum memilih peran (role NULL) harus memilihnya dulu.
        if ($user->role === null) {
            return redirect('/daftar/peran');
        }

        return redirect()->intended('/');
    }

    // Pesan untuk pengguna yang dibatasi. Alasan internal dari Admin tidak ditampilkan.
    private function restrictionMessage(UserSanction $restriction): string
    {
        if ($restriction->type === SanctionType::Ban) {
            return 'Akun Anda diblokir. Hubungi Admin untuk informasi lebih lanjut.';
        }

        if ($restriction->expires_at === null) {
            return 'Akun Anda sedang ditangguhkan.';
        }

        $until = $restriction->expires_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H.i');

        return "Akun Anda ditangguhkan sampai {$until} WIB.";
    }
}