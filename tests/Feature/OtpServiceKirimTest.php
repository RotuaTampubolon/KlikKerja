<?php

namespace Tests\Feature;

use App\Models\OtpRequest;
use App\Models\User;
use App\Services\Otp\OtpDitolakException;
use App\Services\Otp\OtpSender;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Support\FakeOtpSender;
use Tests\TestCase;

class OtpServiceKirimTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpSender $pengirim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();                                  // waktu dibekukan: hasil test pasti sama
        $this->pengirim = new FakeOtpSender();
        $this->app->instance(OtpSender::class, $this->pengirim); // container memakai pengirim palsu
    }

    private function layanan(): OtpService
    {
        return app(OtpService::class);                        // dibuat ulang agar memakai pengirim terbaru
    }

    private function buatUser(): User
    {
        return User::factory()->seeker()->create();
    }

    // Menjalankan $aksi dan memastikan ia ditolak dengan alasan tertentu.
    private function assertDitolak(string $alasan, callable $aksi): OtpDitolakException
    {
        try {
            $aksi();
        } catch (OtpDitolakException $e) {
            $this->assertSame($alasan, $e->alasan);

            return $e;
        }

        $this->fail("Seharusnya ditolak dengan alasan {$alasan}.");
    }

    public function test_kirim_menyimpan_hash_dan_mengirim_kode_6_digit(): void
    {
        $user = $this->buatUser();

        $otp = $this->layanan()->kirim($user, '081234567890');

        $this->assertCount(1, $this->pengirim->terkirim);                       // terkirim satu kali
        $kode = $this->pengirim->kodeTerakhir();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $kode);               // 6 digit angka
        $this->assertNotSame($kode, $otp->code_hash);                            // yang disimpan bukan kode asli
        $this->assertTrue(Hash::check($kode, $otp->code_hash));                  // tapi cocok dengan hash
        $this->assertSame($user->id, $otp->user_id);
        $this->assertSame(now()->addMinutes(5)->timestamp, $otp->expires_at->timestamp); // berlaku 5 menit
    }

    public function test_nomor_dinormalisasi_sebelum_disimpan_dan_dikirim(): void
    {
        $otp = $this->layanan()->kirim($this->buatUser(), '0812-3456-7890');

        $this->assertSame('6281234567890', $otp->phone_number);
        $this->assertSame('6281234567890', $this->pengirim->terkirim[0]['nomor']);
    }

    public function test_nomor_tidak_valid_ditolak_tanpa_menyimpan_atau_mengirim(): void
    {
        $user = $this->buatUser();

        $this->assertDitolak(OtpDitolakException::NOMOR_TIDAK_VALID, fn () => $this->layanan()->kirim($user, '021555123'));

        $this->assertSame(0, OtpRequest::count());
        $this->assertSame([], $this->pengirim->terkirim);
    }

    public function test_permintaan_kedua_sebelum_60_detik_ditolak(): void
    {
        $user = $this->buatUser();
        $this->layanan()->kirim($user, '081234567890');

        $e = $this->assertDitolak(OtpDitolakException::TERLALU_CEPAT, fn () => $this->layanan()->kirim($user, '081234567890'));

        $this->assertSame(60, $e->sisaDetik);                                    // waktu beku: sisa tepat 60 detik
        $this->assertSame(1, OtpRequest::count());                               // yang ditolak tidak tersimpan

        $this->travel(61)->seconds();                                            // maju 61 detik
        $this->layanan()->kirim($user, '081234567890');                          // sekarang boleh

        $this->assertSame(2, OtpRequest::count());
    }

    public function test_maksimal_5_permintaan_dalam_24_jam(): void
    {
        $user = $this->buatUser();

        for ($i = 0; $i < OtpService::MAKS_PERMINTAAN_PER_HARI; $i++) {
            $this->layanan()->kirim($user, '081234567890');
            $this->travel(OtpService::JEDA_KIRIM_ULANG_DETIK + 1)->seconds();    // lewati jeda tiap kali
        }

        // Permintaan ke-6 ditolak karena KUOTA, bukan karena jeda (jeda sudah terlewati).
        $this->assertDitolak(OtpDitolakException::KUOTA_HABIS, fn () => $this->layanan()->kirim($user, '081234567890'));

        $this->travel(25)->hours();                                              // 24 jam berlalu
        $this->layanan()->kirim($user, '081234567890');                          // kuota pulih

        $this->assertSame(6, OtpRequest::count());
    }

    public function test_kuota_nomor_berlaku_lintas_akun_walau_ditulis_beda_format(): void
    {
        $penyerang = $this->buatUser();
        $korban = $this->buatUser();

        for ($i = 0; $i < OtpService::MAKS_PERMINTAAN_PER_HARI; $i++) {
            $this->layanan()->kirim($penyerang, '081234567890');
            $this->travel(OtpService::JEDA_KIRIM_ULANG_DETIK + 1)->seconds();
        }

        // Akun lain, nomor yang sama tetapi ditulis dengan format berbeda: tetap kena batas.
        $this->assertDitolak(OtpDitolakException::KUOTA_HABIS, fn () => $this->layanan()->kirim($korban, '+62 812-3456-7890'));
    }

    public function test_jeda_juga_berlaku_untuk_nomor_yang_sama_lintas_akun(): void
    {
        $this->layanan()->kirim($this->buatUser(), '081234567890');

        $this->assertDitolak(OtpDitolakException::TERLALU_CEPAT, fn () => $this->layanan()->kirim($this->buatUser(), '6281234567890'));
    }

    public function test_pengirim_yang_gagal_tidak_menghabiskan_kuota(): void
    {
        $this->app->instance(OtpSender::class, new class implements OtpSender {
            public function kirim(string $nomor, string $kode): void
            {
                throw new RuntimeException('Gateway WhatsApp mati.');
            }
        });

        try {
            $this->layanan()->kirim($this->buatUser(), '081234567890');
            $this->fail('Seharusnya melempar RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('Gateway WhatsApp mati.', $e->getMessage());
        }

        $this->assertSame(0, OtpRequest::count());                               // baris OTP ikut dibatalkan
    }
}