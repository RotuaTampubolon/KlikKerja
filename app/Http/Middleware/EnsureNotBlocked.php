<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBlocked
{
    // Menutup celah dari Tahap 2: pengguna yang SUDAH login lalu diblokir atau
    // disuspend langsung dikeluarkan, tidak menunggu login berikutnya.
    public function handle(Request $request, Closure $next): Response
    {
        $restriction = $request->user()?->currentRestriction();

        if ($restriction !== null) {
            // Langkah logout yang sama seperti LogoutController.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['google' => $restriction->blockedMessage()]);
        }

        return $next($request);
    }
}