<?php

namespace App\Services\Otp;

use RuntimeException;

// Dilempar saat permintaan OTP DITOLAK karena melanggar aturan (bukan karena sistem rusak).
// Membawa "alasan" berupa kode tetap, supaya controller bisa memilih pesan/respons yang tepat.
class OtpDitolakException extends RuntimeException
{
    // Alasan saat MEMINTA kode.
    public const NOMOR_TIDAK_VALID = 'nomor_tidak_valid';     // format nomor salah
    public const TERLALU_CEPAT = 'terlalu_cepat';             // belum lewat jeda 60 detik
    public const KUOTA_HABIS = 'kuota_habis';                 // sudah 5 permintaan dalam 24 jam

    // Alasan saat MEMERIKSA kode yang diketik pengguna.
    public const TIDAK_ADA_PERMINTAAN = 'tidak_ada_permintaan'; // belum pernah meminta kode untuk nomor ini
    public const KEDALUWARSA = 'kedaluwarsa';                   // lewat 5 menit
    public const PERCOBAAN_HABIS = 'percobaan_habis';           // sudah 5 kali salah
    public const KODE_SALAH = 'kode_salah';                     // kode tidak cocok

    // "public readonly" = properti publik yang hanya bisa diisi sekali, lewat constructor.
    public function __construct(
        public readonly string $alasan,
        string $pesan,
        public readonly int $sisaDetik = 0,      // diisi hanya untuk alasan TERLALU_CEPAT
        public readonly int $sisaPercobaan = 0,  // diisi hanya untuk alasan KODE_SALAH
    ) {
        parent::__construct($pesan); // pesan ramah untuk ditampilkan ke pengguna
    }

    // "Pabrik" kecil supaya pemanggil cukup menulis: throw OtpDitolakException::kuotaHabis();
    public static function nomorTidakValid(): self
    {
        return new self(self::NOMOR_TIDAK_VALID, 'Nomor WhatsApp tidak valid. Gunakan nomor seluler Indonesia, misalnya 0812xxxxxxxx.');
    }

    public static function terlaluCepat(int $sisaDetik): self
    {
        return new self(self::TERLALU_CEPAT, "Tunggu {$sisaDetik} detik sebelum meminta kode lagi.", $sisaDetik);
    }

    public static function kuotaHabis(): self
    {
        return new self(self::KUOTA_HABIS, 'Batas permintaan kode sudah tercapai. Coba lagi nanti.');
    }

    public static function tidakAdaPermintaan(): self
    {
        return new self(self::TIDAK_ADA_PERMINTAAN, 'Belum ada kode untuk nomor ini. Minta kode terlebih dahulu.');
    }

    public static function kedaluwarsa(): self
    {
        return new self(self::KEDALUWARSA, 'Kode sudah kedaluwarsa. Minta kode baru.');
    }

    public static function percobaanHabis(): self
    {
        return new self(self::PERCOBAAN_HABIS, 'Terlalu banyak percobaan yang salah. Minta kode baru.');
    }

    public static function kodeSalah(int $sisaPercobaan): self
    {
        $pesan = $sisaPercobaan > 0
            ? "Kode salah. Sisa percobaan: {$sisaPercobaan}."
            : 'Kode salah. Percobaan habis, minta kode baru.';

        return new self(self::KODE_SALAH, $pesan, 0, $sisaPercobaan);
    }
}