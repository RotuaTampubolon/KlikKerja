<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            // when(): bagian where hanya dipasang kalau ada kata kunci.
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString(); // kata kunci tetap terbawa saat pindah halaman

        return view('admin.pengguna.index', compact('users', 'q'));
    }

    // {user} di route otomatis menjadi model User (404 kalau tidak ada).
    public function show(User $user): View
    {
        return view('admin.pengguna.show', [
            'user' => $user,
            'restriction' => $user->currentRestriction(),
            // with('admin'): ambil nama Admin sekaligus, bukan satu query per baris.
            'sanctions' => $user->sanctions()->with('admin')->latest('id')->get(),
        ]);
    }
}