<?php

namespace App\Services\Verification;

use App\Enums\AdminTaskStatus;
use App\Enums\AdminTaskType;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\AdminTask;
use App\Models\User;
use App\Models\Verification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

// Mengatur keputusan Admin atas pengajuan verifikasi identitas (setujui atau tolak).
class AdminVerificationService
{
    // Laravel menyuntikkan pengirim pemberitahuan (sementara: LogVerificationNotifier).
    public function __construct(private VerificationNotifier $pemberitahu)
    {
    }

    // Menyetujui pengajuan.
    // Melempar VerificationSudahDiputuskanException jika pengajuan sudah punya keputusan,
    // atau NomorWhatsAppSudahDipakaiException jika nomornya sudah dipakai akun lain.
    public function setujui(Admin $admin, Verification $verification): void
    {
        // Semua perubahan data dalam SATU transaksi: semuanya berhasil atau tidak ada yang berubah.
        $diputuskan = DB::transaction(function () use ($admin, $verification) {
            $v = $this->kunciDanPastikanMenunggu($verification);
            $user = $v->user;

            // users.whatsapp_number itu UNIQUE. Periksa dulu supaya Admin mendapat pesan yang jelas,
            // bukan error database. Akun pemilik pengajuan sendiri dikecualikan dari pemeriksaan.
            $dipakaiAkunLain = User::query()
                ->where('whatsapp_number', $v->phone_number)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($dipakaiAkunLain) {
                throw new NomorWhatsAppSudahDipakaiException($v->phone_number);
            }

            // Keputusan pada pengajuan: status, siapa yang memutuskan, dan kapan.
            $v->status = VerificationStatus::Verified;
            $v->reviewed_by = $admin->id;
            $v->reviewed_at = now();
            $v->save();

            // Status akun pengguna ikut berubah, dan nomor WhatsApp resmi diisi dari nomor yang SUDAH lolos
            // OTP di pengajuan ini. Bukan dari input Admin: inilah satu-satunya tempat kolom itu diisi.
            $user->verification_status = VerificationStatus::Verified;
            $user->whatsapp_number = $v->phone_number;
            $user->save();

            // Kartu di antrean Admin ditandai selesai.
            $this->selesaikanTugas($admin, $v, 'disetujui');

            // Audit log DI DALAM transaksi: keputusan dan jejaknya menjadi satu paket. Kalau transaksi
            // dibatalkan, jejaknya ikut batal, jadi tidak mungkin ada "disetujui" tanpa jejak,
            // atau jejak "disetujui" tanpa persetujuan.
            AuditLogger::log($admin, 'verification.approved', $v, ['phone_number' => $v->phone_number]);

            return $v;
        });

        // Pemberitahuan DI LUAR transaksi (alasannya dijelaskan di method beritahu()).
        $this->beritahu(
            $diputuskan->user,
            $diputuskan,
            'Verifikasi identitas Anda disetujui. Anda kini dapat menggunakan fitur utama KlikKerja.'
        );
    }

    // Menolak pengajuan. $alasan wajib (dijaga Form Request) dan akan ditampilkan kepada pengguna.
    public function tolak(Admin $admin, Verification $verification, string $alasan): void
    {
        $diputuskan = DB::transaction(function () use ($admin, $verification, $alasan) {
            $v = $this->kunciDanPastikanMenunggu($verification);
            $user = $v->user;

            $v->status = VerificationStatus::Rejected;
            $v->rejected_reason = $alasan;
            $v->reviewed_by = $admin->id;
            $v->reviewed_at = now();
            $v->save();

            // Akun berstatus Ditolak: pengguna boleh mengirim ulang dokumen (diatur VerificationService).
            // whatsapp_number SENGAJA tidak diisi: nomornya belum disahkan oleh pemeriksaan manusia.
            $user->verification_status = VerificationStatus::Rejected;
            $user->save();

            $this->selesaikanTugas($admin, $v, 'ditolak: '.$alasan);
            AuditLogger::log($admin, 'verification.rejected', $v, ['alasan' => $alasan]);

            return $v;
        });

        $this->beritahu(
            $diputuskan->user,
            $diputuskan,
            "Verifikasi identitas Anda ditolak. Alasan: {$alasan} Silakan unggah ulang dokumen Anda."
        );
    }

    // Mengunci baris pengajuan lalu memastikan statusnya masih Menunggu.
    // Kunci membuat dua Admin yang menekan tombol bersamaan dilayani satu per satu: yang kedua
    // membaca status yang sudah berubah dan ditolak. Status Disetujui/Ditolak adalah status akhir (PRD 7.3).
    private function kunciDanPastikanMenunggu(Verification $verification): Verification
    {
        $v = Verification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();

        if ($v->status !== VerificationStatus::Pending) {
            throw new VerificationSudahDiputuskanException($v->status);
        }

        return $v;
    }

    // Menandai kartu antrean milik pengajuan ini sebagai Selesai, lengkap dengan siapa dan hasilnya.
    // Tanpa ini, kartu tetap "open" selamanya dan hitungan antrean di dasbor Admin menjadi salah.
    private function selesaikanTugas(Admin $admin, Verification $v, string $hasil): void
    {
        AdminTask::query()
            ->where('type', AdminTaskType::Verification->value)
            ->where('ref_type', 'verification')
            ->where('ref_id', $v->id)
            ->where('status', '!=', AdminTaskStatus::Done->value)
            ->update([
                'status' => AdminTaskStatus::Done->value,
                'handled_by' => $admin->id,
                'handling_result' => $hasil,
                'handled_at' => now(),
            ]);
    }

    // Memberi tahu pengguna. Dilakukan SETELAH transaksi selesai, dan kegagalannya TIDAK boleh
    // membatalkan keputusan. Alasannya: pemberitahuan adalah efek ke dunia luar yang tidak bisa
    // di-rollback seperti baris database. Keputusan Admin sudah sah. Kalau pemberitahuan gagal,
    // cukup dicatat sebagai error (report) supaya terlihat, bukan membuat Admin melihat halaman error.
    private function beritahu(User $user, Verification $v, string $pesan): void
    {
        try {
            $this->pemberitahu->kirimHasil($user, $v, $pesan);
        } catch (Throwable $e) {
            report($e);
        }
    }
}