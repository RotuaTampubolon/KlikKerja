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

        // attempt: cari Admin berdasarkan email, lalu cocokkan hash password.
        // Guard 'admin' dipakai, jadi sesi pengguna biasa tidak tersentuh.
        if (! Auth::guard('admin')->attempt($credentials)) {
            // Pesan SAMA untuk email salah maupun password salah (cegah user enumeration).
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email'); // isi ulang email, tapi JANGAN password
        }

        // Ganti ID sesi setelah login (cegah session fixation).
        $request->session()->regenerate();

        // 6b nanti: di sini kita sisipkan langkah kode TOTP sebelum benar-benar login.
        return redirect()->intended('/admin');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}