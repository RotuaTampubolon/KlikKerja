<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    // Controller satu aksi: __invoke dipanggil langsung oleh route.
    public function __invoke(Request $request): RedirectResponse
    {
        // 1. Keluarkan pengguna dari guard 'web'.
        Auth::logout();

        // 2. Hancurkan sesi lama agar ID sesinya tidak bisa dipakai lagi.
        $request->session()->invalidate();

        // 3. Buat token CSRF baru untuk sesi berikutnya.
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}