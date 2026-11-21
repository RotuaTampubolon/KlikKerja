<?php

namespace App\Services\Otp;

// "Kontrak" untuk semua pengirim OTP. Interface hanya menyatakan APA yang bisa dilakukan,
// bukan BAGAIMANA caranya. Cara kerjanya ditulis di kelas yang mengimplementasikannya.
interface OtpSender
{
    // Mengirim kode OTP ke sebuah nomor. $nomor sudah dinormalisasi (628...).
    // Harus melempar exception jika gagal, supaya pemanggil tidak mengira OTP terkirim.
    public function kirim(string $nomor, string $kode): void;
}