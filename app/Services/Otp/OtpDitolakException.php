<?php

namespace App\Services\Otp;

use RuntimeException;

// Dilempar saat permintaan OTP DITOLAK karena melanggar aturan (bukan karena sistem rusak).
// Membawa "alasan" berupa kode tetap, supaya controller bisa memilih pesan/respons yang tepat.
class OtpDitolakException extends RuntimeException
{
    public const NOMOR_TIDAK_VALID = 'nomor_tidak_valid'; // format nomor salah
    public const TERLALU_CEPAT = 'terlalu_cepat';         // belum lewat jeda 60 detik
    public const KUOTA_HABIS = 'kuota_habis';             // sudah 5 permintaan dalam 24 jam

    // "public readonly" = properti publik yang hanya bisa diisi sekali, lewat constructor.
    public function __construct(
        public readonly string $alasan,
        string $pesan,
        public readonly int $sisaDetik = 0, // diisi hanya untuk alasan TERLALU_CEPAT
    ) {
        parent::__construct($pesan); // pesan ramah untuk ditampilkan ke pengguna
    }

    // Tiga "pabrik" kecil supaya pemanggil cukup menulis: throw OtpDitolakException::kuotaHabis();
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
}