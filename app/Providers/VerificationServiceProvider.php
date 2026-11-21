<?php

namespace App\Providers;

use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

// Mendaftarkan service modul Verifikasi ke "container" Laravel.
class VerificationServiceProvider extends ServiceProvider
{
    // register() berjalan saat aplikasi menyala. Isinya: "kalau ada yang meminta OtpSender,
    // berikan objek ini". Pemilihan objek mengikuti OTP_DRIVER di .env.
    public function register(): void
    {
        $this->app->bind(OtpSender::class, function ($app) {
            // Nilai OTP_DRIVER dari .env, lewat config/klikkerja.php milik Rotua. Bawaannya 'log'.
            $driver = config('klikkerja.otp_driver');

            // Driver 'log' menulis KODE OTP ke file log. Di production itu kebocoran,
            // jadi dilarang keras: lebih baik error daripada diam-diam membocorkan kode.
            if ($driver === 'log') {
                if ($app->isProduction()) {
                    throw new RuntimeException('Driver OTP "log" tidak boleh dipakai di production.');
                }

                return new LogOtpSender();
            }

            // Driver lain (mis. WhatsApp API sungguhan) belum ada. Nama yang salah ketik
            // harus langsung ketahuan, bukan diam-diam dianggap 'log'.
            throw new InvalidArgumentException("Driver OTP '{$driver}' tidak dikenal.");
        });
    }
}