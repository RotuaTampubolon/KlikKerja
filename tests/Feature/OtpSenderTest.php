<?php

namespace Tests\Feature;

use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

// Test pengirim OTP. Tidak butuh database, jadi tidak memakai RefreshDatabase.
class OtpSenderTest extends TestCase
{
    public function test_driver_log_menghasilkan_log_sender(): void
    {
        config(['klikkerja.otp_driver' => 'log']);   // atur driver untuk test ini saja

        $this->assertInstanceOf(LogOtpSender::class, app(OtpSender::class));
    }

    public function test_log_sender_menulis_nomor_dan_kode_ke_log(): void
    {
        Log::spy();                                  // pengintai: mencatat semua pemanggilan Log

        (new LogOtpSender())->kirim('6281234567890', '123456');

        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $pesan) => str_contains($pesan, '6281234567890') && str_contains($pesan, '123456')
        );
    }

    public function test_driver_tidak_dikenal_ditolak(): void
    {
        config(['klikkerja.otp_driver' => 'telegram']);
        $this->expectException(InvalidArgumentException::class);   // harapannya: error ini muncul

        app(OtpSender::class);
    }

    public function test_driver_log_dilarang_di_production(): void
    {
        config(['klikkerja.otp_driver' => 'log']);
        $this->app->detectEnvironment(fn () => 'production');      // pura-pura berjalan di production
        $this->expectException(RuntimeException::class);

        app(OtpSender::class);
    }
}