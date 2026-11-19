<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class AdminTwoFactorController extends Controller
{
    // Batas kode salah dalam satu sesi "menunggu kode".
    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        // Tanpa sesi "menunggu kode" yang valid, halaman ini tidak boleh dibuka.
        if ($this->pendingAdmin($request) === null) {
            return redirect()->route('admin.login');
        }

        return view('admin.2fa');
    }

    // Google2FA diberikan otomatis oleh Laravel (dependency injection).
    public function store(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin === null) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Sesi login berakhir. Silakan masuk kembali.']);
        }

        $request->validate(['code' => ['required', 'digits:6']]);

        // Argumen ke-3 (window = 1): terima kode dari 30 detik sebelum/sesudah sekarang,
        // untuk menoleransi jam ponsel yang sedikit meleset.
        if ($google2fa->verifyKey($admin->two_factor_secret, $request->input('code'), 1)) {
            $request->session()->forget('admin_2fa');
            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        // Kode salah: hitung, dan putus sesi kalau sudah terlalu banyak.
        $request->session()->increment('admin_2fa.attempts');

        if ($request->session()->get('admin_2fa.attempts') >= self::MAX_ATTEMPTS) {
            $request->session()->forget('admin_2fa');

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Terlalu banyak kode salah. Silakan masuk kembali.']);
        }

        return back()->withErrors(['code' => 'Kode salah atau sudah kedaluwarsa.']);
    }

    // Admin yang sedang menunggu kode, atau null kalau sesinya tidak ada/kedaluwarsa.
    private function pendingAdmin(Request $request): ?Admin
    {
        $pending = $request->session()->get('admin_2fa');

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('admin_2fa');

            return null;
        }

        $admin = Admin::find($pending['id']);

        // Pengaman: secret bisa saja dihapus sejak langkah password.
        return $admin?->two_factor_secret === null ? null : $admin;
    }
}