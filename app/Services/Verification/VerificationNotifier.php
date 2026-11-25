<?php

namespace App\Services\Verification;

use App\Models\User;
use App\Models\Verification;

// "Kontrak" pengiriman pemberitahuan hasil verifikasi kepada pengguna.
// Service notifikasi web milik Anggota 4 belum ada. Panduan membolehkan stub dulu, jadi kita
// bergantung pada interface ini. Nanti cukup membuat satu kelas baru yang memanggil
// NotificationService::send(...) lalu mengganti pendaftarannya di VerificationServiceProvider.
// AdminVerificationService tidak perlu diubah sama sekali.
interface VerificationNotifier
{
    // $pesan sudah disusun oleh AdminVerificationService, tugas notifier hanya MENGANTARKANNYA.
    public function kirimHasil(User $user, Verification $verification, string $pesan): void;
}