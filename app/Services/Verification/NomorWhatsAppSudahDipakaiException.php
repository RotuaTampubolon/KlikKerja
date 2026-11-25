<?php

namespace App\Services\Verification;

use RuntimeException;

// Dilempar saat Admin menyetujui pengajuan, tetapi nomor WhatsApp-nya sudah terdaftar pada akun LAIN.
// Kolom users.whatsapp_number bersifat UNIQUE (satu nomor = satu akun), jadi persetujuan seperti itu
// pasti gagal di database. Lebih baik dicegah dengan pesan yang jelas daripada berakhir sebagai error 500.
class NomorWhatsAppSudahDipakaiException extends RuntimeException
{
    public function __construct(public readonly string $nomor)
    {
        parent::__construct("Nomor {$nomor} sudah terdaftar pada akun lain. Tolak pengajuan ini dengan alasan yang sesuai.");
    }
}