<?php

namespace App\Services\Verification;

use RuntimeException;

// Dilempar saat pengiriman dokumen DITOLAK karena syarat belum terpenuhi (bukan karena sistem rusak).
class VerificationDitolakException extends RuntimeException
{
    public const OTP_BELUM_SELESAI = 'otp_belum_selesai';         // nomor WhatsApp belum diverifikasi
    public const SEDANG_DIPERIKSA = 'sedang_diperiksa';           // dokumen sebelumnya masih menunggu Admin
    public const SUDAH_TERVERIFIKASI = 'sudah_terverifikasi';     // tidak perlu mengirim lagi

    public function __construct(public readonly string $alasan, string $pesan)
    {
        parent::__construct($pesan);
    }

    public static function otpBelumSelesai(): self
    {
        return new self(self::OTP_BELUM_SELESAI, 'Verifikasi nomor WhatsApp Anda terlebih dahulu sebelum mengunggah dokumen.');
    }

    public static function sedangDiperiksa(): self
    {
        return new self(self::SEDANG_DIPERIKSA, 'Dokumen Anda sedang diperiksa. Tunggu hasilnya sebelum mengirim lagi.');
    }

    public static function sudahTerverifikasi(): self
    {
        return new self(self::SUDAH_TERVERIFIKASI, 'Akun Anda sudah terverifikasi.');
    }
}