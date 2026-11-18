<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ConsentType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChooseRoleRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleSelectionController extends Controller
{
    // Menampilkan form pilih peran.
    public function show(Request $request): View|RedirectResponse
    {
        // Peran hanya boleh dipilih sekali: kalau sudah terisi, jangan tampilkan form.
        if ($request->user()->role !== null) {
            return redirect('/');
        }

        return view('auth.pilih-peran');
    }

    // Menyimpan peran + persetujuan.
    public function store(ChooseRoleRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Cek ulang di server, karena form bisa dikirim langsung tanpa membuka halaman
        // (misalnya lewat tab lama). Jangan percaya bahwa "show" sudah menyaring.
        if ($user->role !== null) {
            return redirect('/');
        }

        // Data sudah lolos validasi, jadi aman diubah menjadi objek enum.
        $role = $request->enum('role', Role::class);

        // Transaksi: tiga penulisan ini berhasil semua atau dibatalkan semua.
        DB::transaction(function () use ($user, $role) {
            // Penugasan langsung (bukan mass assignment): 'role' memang tidak fillable.
            $user->role = $role;
            $user->save();

            $now = now();
            $user->consents()->createMany([
                [
                    'type' => ConsentType::Terms,
                    'version' => config('legal.terms_version'),
                    'consented_at' => $now,
                ],
                [
                    'type' => ConsentType::Privacy,
                    'version' => config('legal.privacy_version'),
                    'consented_at' => $now,
                ],
            ]);
        });

        // Sementara ke beranda. Nanti diarahkan ke pelengkapan profil.
        return redirect('/');
    }
}