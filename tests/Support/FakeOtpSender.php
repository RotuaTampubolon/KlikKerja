<?php

namespace Tests\Support;

use App\Services\Otp\OtpSender;

// Pengirim palsu khusus test: menyimpan kode di memori supaya test bisa "membacanya".
// Penting, karena di database kode hanya tersimpan sebagai hash dan tidak bisa dibalik.
class FakeOtpSender implements OtpSender
{
    // Daftar semua kiriman: [['nomor' => '628...', 'kode' => '123456'], ...]
    public array $terkirim = [];

    public function kirim(string $nomor, string $kode): void
    {
        $this->terkirim[] = ['nomor' => $nomor, 'kode' => $kode];
    }

    // Kode dari pengiriman paling akhir, atau null jika belum ada.
    public function kodeTerakhir(): ?string
    {
        return $this->terkirim === [] ? null : $this->terkirim[array_key_last($this->terkirim)]['kode'];
    }
}