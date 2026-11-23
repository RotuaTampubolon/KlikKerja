<?php

namespace App\Services\Verification;

use App\Enums\AdminTaskType;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\Verification;
use App\Services\AdminTaskQueue;
use App\Services\AuditLogger;
use App\Services\Otp\OtpService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

// Mengatur pengiriman dokumen identitas (KTP dan selfie).
class VerificationService
{
    // Disk privat dari Tahap 1: tanpa URL, tidak bisa dibuka lewat alamat web.
    public const DISK = 'private';

    public function __construct(private OtpService $otp)
    {
    }

    // Menyimpan dokumen, membuat pengajuan, mengubah status menjadi Menunggu pemeriksaan,
    // lalu memasukkannya ke antrean Admin. Melempar VerificationDitolakException jika syarat belum terpenuhi.
    public function kirimDokumen(User $user, UploadedFile $ktp, UploadedFile $selfie): Verification
    {
        // Syarat dari panduan: nomor WhatsApp harus sudah lolos OTP. Nomor itulah yang dicatat di pengajuan.
        $nomor = $this->otp->nomorTerverifikasi($user);

        if ($nomor === null) {
            throw VerificationDitolakException::otpBelumSelesai();
        }

        $disk = Storage::disk(self::DISK);
        $tersimpan = []; // path file yang sudah ditulis, dicatat agar bisa dihapus kalau ada yang gagal

        try {
            $verification = DB::transaction(function () use ($user, $nomor, $ktp, $selfie, $disk, &$tersimpan) {
                // Kunci baris user lalu baca status TERBARU, supaya dua pengiriman bersamaan
                // tidak sama-sama lolos.
                $terkunci = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $this->pastikanBolehMengirim($terkunci);

                // File ditulis SETELAH pemeriksaan di atas, jadi permintaan yang ditolak tidak meninggalkan file.
                // putFile membuat nama ACAK (nama asli dari pengguna tidak dipakai) di folder ktp/ dan selfie/.
                $ktpPath = $disk->putFile('ktp', $ktp);
                $tersimpan[] = $ktpPath;

                $selfiePath = $disk->putFile('selfie', $selfie);
                $tersimpan[] = $selfiePath;

                // Satu pengiriman = satu baris baru. Pengajuan lama yang ditolak tetap ada sebagai riwayat.
                $verification = new Verification([
                    'phone_number' => $nomor,
                    'ktp_path' => $ktpPath,
                    'selfie_path' => $selfiePath,
                ]);
                $verification->user_id = $terkunci->id; // tidak fillable: diisi kode server
                $verification->save();                  // status otomatis 'pending'

                $terkunci->verification_status = VerificationStatus::Pending;
                $terkunci->save();

                // Masuk antrean Admin dan tercatat di jejak aktivitas.
                AdminTaskQueue::push(AdminTaskType::Verification, 'verification', $verification->id);
                AuditLogger::log($terkunci, 'verification.submitted', $verification);

                return $verification;
            });
        } catch (Throwable $e) {
            // Transaksi database sudah dibatalkan otomatis, tetapi FILE tidak ikut dibatalkan.
            // Hapus manual supaya tidak ada foto KTP yatim di server.
            foreach ($tersimpan as $path) {
                $disk->delete($path);
            }

            throw $e;
        }

        $user->refresh(); // objek yang dipegang pemanggil ikut menunjukkan status terbaru

        return $verification;
    }

    // Hanya akun yang belum pernah mengirim atau yang sebelumnya ditolak yang boleh mengirim dokumen.
    private function pastikanBolehMengirim(User $user): void
    {
        match ($user->verification_status) {
            VerificationStatus::Verified => throw VerificationDitolakException::sudahTerverifikasi(),
            VerificationStatus::Pending => throw VerificationDitolakException::sedangDiperiksa(),
            default => null, // Unverified atau Rejected: boleh
        };
    }
}