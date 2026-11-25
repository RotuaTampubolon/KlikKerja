<?php

namespace App\Services\Verification;

use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\Verification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

// Mengatur keputusan Admin atas pengajuan verifikasi identitas.
class AdminVerificationService
{
    // Menyetujui pengajuan. Melempar exception jika pengajuan sudah diputuskan sebelumnya.
    public function setujui(Admin $admin, Verification $verification): void
    {
        DB::transaction(function () use ($admin, $verification) {
            // Kunci baris pengajuan selama transaksi supaya dua Admin tidak memutuskan sekaligus.
            $v = Verification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();

            // Pengajuan yang sudah diputuskan tidak boleh diubah lagi (PRD 7.3: status akhir tidak
            // dapat diubah). Kalau Admin sudah klik Setujui/Tolak, tombol itu tidak boleh bisa
            // diklik lagi oleh Admin lain yang membuka halaman yang sama.
            if ($v->status !== VerificationStatus::Pending) {
                throw new VerificationSudahDiputuskanException($v->status);
            }

            // Simpan siapa yang memutuskan dan kapan (untuk jejak audit dan tampilan).
            $v->status = VerificationStatus::Verified;
            $v->reviewed_by = $admin->id;
            $v->reviewed_at = now();
            $v->save();

            // Status akun pengguna ikut berubah.
            $user = $v->user;
            $user->verification_status = VerificationStatus::Verified;

            // Nomor WhatsApp resmi diisi dari nomor yang sudah lolos OTP di pengajuan ini.
            // Bukan dari input Admin, bukan dari kolom lain. Ini satu-satunya tempat pengisian.
            $user->whatsapp_number = $v->phone_number;
            $user->save();
        });

        // Dicatat SETELAH transaksi berhasil. Kalau dicatat di dalam dan transaksi rollback,
        // log "disetujui" akan tercatat padahal persetujuannya dibatalkan.
        AuditLogger::log($admin, 'verification.approved', $verification, [
            'phone_number' => $verification->phone_number,
        ]);
    }

    // Menolak pengajuan. Alasan wajib diisi (dijaga oleh Form Request nanti).
    public function tolak(Admin $admin, Verification $verification, string $alasan): void
    {
        DB::transaction(function () use ($admin, $verification, $alasan) {
            $v = Verification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();

            if ($v->status !== VerificationStatus::Pending) {
                throw new VerificationSudahDiputuskanException($v->status);
            }

            $v->status = VerificationStatus::Rejected;
            $v->rejected_reason = $alasan;
            $v->reviewed_by = $admin->id;
            $v->reviewed_at = now();
            $v->save();

            // Status akun kembali memberi kesempatan pengguna mengirim ulang (PRD 7.3).
            $user = $v->user;
            $user->verification_status = VerificationStatus::Rejected;
            // whatsapp_number TIDAK diisi: nomor belum diverifikasi oleh manusia.
            $user->save();
        });

        AuditLogger::log($admin, 'verification.rejected', $verification, [
            'alasan' => $alasan,
        ]);
    }
}