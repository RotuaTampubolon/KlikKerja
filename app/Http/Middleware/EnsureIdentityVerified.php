<?php

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdentityVerified
{
    // Hanya status Verified yang lolos (unverified, pending, rejected ditolak).
    // /verifikasi adalah halaman milik Anggota 2.
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->verification_status !== VerificationStatus::Verified) {
            return redirect('/verifikasi');
        }

        return $next($request);
    }
}