<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log; // pintu masuk ke catatan log Laravel

// Pengirim "pura-pura" untuk tahap pengembangan: kode OTP hanya ditulis ke file log
// (storage/logs/laravel.log), tidak benar-benar dikirim ke WhatsApp.
class LogOtpSender implements OtpSender
{
    public function kirim(string $nomor, string $kode): void
    {
        // Developer membaca kode dari log untuk menyelesaikan alur verifikasi di komputernya.
        Log::info("OTP untuk {$nomor}: {$kode}");
    }
}