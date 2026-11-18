<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleChosen
{
    // Dipasang SETELAH 'auth'. Pengguna yang belum memilih peran dikirim ke form peran.
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === null) {
            return redirect('/daftar/peran');
        }

        return $next($request);
    }
}