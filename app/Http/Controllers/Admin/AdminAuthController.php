<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function create(): View
    {
        return view('admin.masuk');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cek kredensial TANPA login. getProvider() = "daftar akun" milik guard admin.
        $provider = Auth::guard('admin')->getProvider();
        $admin = $provider->retrieveByCredentials($credentials);

        if (! $admin || ! $provider->validateCredentials($admin, $credentials)) {
            // Pesan SAMA untuk email salah maupun password salah (cegah user enumeration).
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        // Fail closed: tanpa 2FA aktif, Admin tidak boleh masuk.
        if ($admin->two_factor_secret === null) {
            return back()
                ->withErrors(['email' => 'Autentikasi dua langkah belum diaktifkan untuk akun ini. Hubungi pengelola sistem.'])
                ->onlyInput('email');
        }

        // Password benar, tapi BELUM login. Catat "menunggu kode" selama 5 menit.
        $request->session()->put('admin_2fa', [
            'id' => $admin->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'attempts' => 0,
        ]);
        $request->session()->regenerate();

        return redirect()->route('admin.2fa');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}