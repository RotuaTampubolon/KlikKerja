<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    // Dipakai sebagai role:seeker, role:employer, role:admin,
    // atau beberapa sekaligus: role:seeker,employer.
    // Teks setelah titik dua dipecah per koma dan tiba sebagai argumen ($roles).
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        foreach ($roles as $role) {
            if ($this->hasRole($request, $role)) {
                return $next($request);
            }
        }

        // Salah peran = 403, bukan redirect: pengguna sudah dikenal, hanya tidak berhak.
        abort(403);
    }

    private function hasRole(Request $request, string $role): bool
    {
        // Admin ada di tabel 'admins' dengan guard sendiri, bukan kolom users.role.
        if ($role === 'admin') {
            return Auth::guard('admin')->check();
        }

        // users.role adalah enum (atau null), jadi dibandingkan lewat ->value.
        return $request->user()?->role?->value === $role;
    }
}