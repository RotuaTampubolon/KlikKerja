<?php

namespace App\Services\Verification;

use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Facades\Log;

// Stub sementara: pesan hanya ditulis ke file log (storage/logs/laravel.log), belum masuk ke
// halaman notifikasi pengguna. Diganti dengan pengirim sungguhan saat NotificationService milik Anggota 4 siap.
class LogVerificationNotifier implements VerificationNotifier
{
    public function kirimHasil(User $user, Verification $verification, string $pesan): void
    {
        Log::info("Notifikasi verifikasi untuk user #{$user->id} (pengajuan #{$verification->id}): {$pesan}");
    }
}