<?php

namespace Tests\Feature;

use App\Models\OtpRequest;
use App\Models\User;
use App\Services\Otp\OtpDitolakException;
use App\Services\Otp\OtpSender;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeOtpSender;
use Tests\TestCase;

class OtpServiceCekTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '081234567890';

    private FakeOtpSender $pengirim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        $this->pengirim = new FakeOtpSender();
        $this->app->instance(OtpSender::class, $this->pengirim);
    }

    private function layanan(): OtpService
    {
        return app(OtpService::class);
    }

    private function buatUser(): User
    {
        return User::factory()->seeker()->create();
    }

    // Meminta OTP lalu mengembalikan kode aslinya (dibaca dari pengirim palsu).
    private function mintaKode(User $user, string $nomor = self::NOMOR): string
    {
        $this->layanan()->kirim($user, $nomor);

        return $this->pengirim->kodeTerakhir();
    }

    // Menghasilkan kode 6 digit yang PASTI berbeda dari $kode (tidak bergantung pada keberuntungan).
    private function kodeSalah(string $kode): string
    {
        return $kode === '000000' ? '111111' : '000000';
    }

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

    public function test_kode_benar_menandai_otp_terverifikasi(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);

        $otp = $this->layanan()->cek($user, self::NOMOR, $kode);

        $this->assertNotNull($otp->verified_at);
        $this->assertSame(now()->timestamp, $otp->verified_at->timestamp);
        $this->assertNotNull(OtpRequest::first()->verified_at);                  // tersimpan di database
    }

    public function test_nomor_beda_format_dan_kode_dengan_spasi_tetap_diterima(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);

        $otp = $this->layanan()->cek($user, '+62 812-3456-7890', "  {$kode}  ");

        $this->assertNotNull($otp->verified_at);
    }

    public function test_kode_salah_ditolak_dan_percobaan_tercatat_di_database(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);

        $e = $this->assertDitolak(OtpDitolakException::KODE_SALAH, fn () => $this->layanan()->cek($user, self::NOMOR, $this->kodeSalah($kode)));

        $this->assertSame(OtpService::MAKS_PERCOBAAN - 1, $e->sisaPercobaan);    // sisa 4
        $this->assertSame(1, OtpRequest::first()->attempts);                     // PENTING: tidak ikut dibatalkan
        $this->assertNull(OtpRequest::first()->verified_at);
    }

    public function test_setelah_5_kali_salah_kode_benar_pun_ditolak(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);

        for ($i = 1; $i <= OtpService::MAKS_PERCOBAAN; $i++) {
            $e = $this->assertDitolak(OtpDitolakException::KODE_SALAH, fn () => $this->layanan()->cek($user, self::NOMOR, $this->kodeSalah($kode)));
            $this->assertSame(OtpService::MAKS_PERCOBAAN - $i, $e->sisaPercobaan);
        }

        $this->assertDitolak(OtpDitolakException::PERCOBAAN_HABIS, fn () => $this->layanan()->cek($user, self::NOMOR, $kode));
        $this->assertNull(OtpRequest::first()->verified_at);
    }

    public function test_kode_kedaluwarsa_setelah_5_menit(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);

        $this->travel(OtpService::MASA_BERLAKU_MENIT * 60 + 1)->seconds();       // 5 menit lewat 1 detik

        $this->assertDitolak(OtpDitolakException::KEDALUWARSA, fn () => $this->layanan()->cek($user, self::NOMOR, $kode));
    }

    public function test_kode_masih_berlaku_tepat_sebelum_5_menit(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);

        $this->travel(OtpService::MASA_BERLAKU_MENIT * 60 - 1)->seconds();       // 4 menit 59 detik

        $this->assertNotNull($this->layanan()->cek($user, self::NOMOR, $kode)->verified_at);
    }

    public function test_tanpa_permintaan_sebelumnya_ditolak(): void
    {
        $this->assertDitolak(OtpDitolakException::TIDAK_ADA_PERMINTAAN, fn () => $this->layanan()->cek($this->buatUser(), self::NOMOR, '123456'));
    }

    public function test_nomor_tidak_valid_ditolak(): void
    {
        $this->assertDitolak(OtpDitolakException::NOMOR_TIDAK_VALID, fn () => $this->layanan()->cek($this->buatUser(), '021555123', '123456'));
    }

    public function test_kode_milik_user_lain_tidak_bisa_dipakai(): void
    {
        $pemilik = $this->buatUser();
        $kode = $this->mintaKode($pemilik);

        // User lain mencoba memakai kode yang benar untuk nomor yang sama: tidak ada OTP atas namanya.
        $this->assertDitolak(OtpDitolakException::TIDAK_ADA_PERMINTAAN, fn () => $this->layanan()->cek($this->buatUser(), self::NOMOR, $kode));
        $this->assertNull(OtpRequest::first()->verified_at);
    }

    public function test_hanya_kode_terbaru_yang_berlaku(): void
    {
        $user = $this->buatUser();
        $kodeLama = $this->mintaKode($user);
        $this->travel(OtpService::JEDA_KIRIM_ULANG_DETIK + 1)->seconds();
        $kodeBaru = $this->mintaKode($user);

        if ($kodeLama === $kodeBaru) {
            $this->markTestSkipped('Kode acak kebetulan sama (peluang 1 banding sejuta).');
        }

        $this->assertDitolak(OtpDitolakException::KODE_SALAH, fn () => $this->layanan()->cek($user, self::NOMOR, $kodeLama));
        $this->assertNotNull($this->layanan()->cek($user, self::NOMOR, $kodeBaru)->verified_at);
    }

    public function test_cek_ulang_setelah_terverifikasi_tidak_mengubah_apa_pun(): void
    {
        $user = $this->buatUser();
        $kode = $this->mintaKode($user);
        $this->layanan()->cek($user, self::NOMOR, $kode);

        $this->layanan()->cek($user, self::NOMOR, $kode);                        // dipanggil lagi: tidak error

        $this->assertSame(0, OtpRequest::first()->attempts);
    }

    public function test_sudah_terverifikasi_hanya_benar_untuk_user_dan_nomor_yang_tepat(): void
    {
        $user = $this->buatUser();
        $this->assertFalse($this->layanan()->sudahTerverifikasi($user, self::NOMOR)); // belum apa-apa

        $kode = $this->mintaKode($user);
        $this->assertFalse($this->layanan()->sudahTerverifikasi($user, self::NOMOR)); // OTP terkirim tapi belum dicek

        $this->layanan()->cek($user, self::NOMOR, $kode);

        $this->assertTrue($this->layanan()->sudahTerverifikasi($user, '+62 812-3456-7890')); // beda format, sama nomor
        $this->assertFalse($this->layanan()->sudahTerverifikasi($user, '081999999999'));     // nomor lain
        $this->assertFalse($this->layanan()->sudahTerverifikasi($this->buatUser(), self::NOMOR)); // user lain
    }

    
    public function test_nomor_terverifikasi_mengembalikan_nomor_milik_user_atau_null(): void
    {
        $user = $this->buatUser();
        $this->assertNull($this->layanan()->nomorTerverifikasi($user));                 // belum apa-apa

        $kode = $this->mintaKode($user);
        $this->assertNull($this->layanan()->nomorTerverifikasi($user));                 // OTP terkirim, belum dicek

        $this->layanan()->cek($user, self::NOMOR, $kode);

        $this->assertSame('6281234567890', $this->layanan()->nomorTerverifikasi($user)); // nomor baku
        $this->assertNull($this->layanan()->nomorTerverifikasi($this->buatUser()));      // user lain tidak terpengaruh
    }
}