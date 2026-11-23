<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\OtpRequest;
use App\Models\User;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeOtpSender;
use Tests\TestCase;

// Test alur lengkap dari sisi browser: halaman, kirim OTP, dan cek OTP.
class VerificationOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '081234567890';
    private const NOMOR_BAKU = '6281234567890';

    private FakeOtpSender $pengirim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();                                     // seperti test Rotua: tanpa build Vite
        $this->freezeTime();                                      // waktu beku agar hasil pasti
        $this->pengirim = new FakeOtpSender();
        $this->app->instance(OtpSender::class, $this->pengirim);  // WhatsApp palsu: kode bisa dibaca test
    }

    private function pencariKerja(): User
    {
        return User::factory()->seeker()->create();
    }

    // verification_status tidak fillable, jadi diubah lewat penugasan langsung (seperti kode server).
    private function denganStatus(User $user, VerificationStatus $status): User
    {
        $user->verification_status = $status;
        $user->save();

        return $user;
    }

    // Login sebagai $user, minta OTP lewat route, lalu kembalikan kode aslinya.
    private function mintaKode(User $user, string $nomor = self::NOMOR): string
    {
        $this->actingAs($user)->post(route('verifikasi.otp.kirim'), ['nomor' => $nomor]);

        return $this->pengirim->kodeTerakhir();
    }

    // ---------- Akses halaman ----------

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/verifikasi')->assertRedirectToRoute('login');
        $this->post('/verifikasi/otp/kirim', ['nomor' => self::NOMOR])->assertRedirectToRoute('login');
        $this->post('/verifikasi/otp/cek', ['nomor' => self::NOMOR, 'kode' => '123456'])->assertRedirectToRoute('login');
    }

    public function test_pengguna_tanpa_peran_diarahkan_memilih_peran(): void
    {
        $user = User::factory()->create(['role' => null]);

        $this->actingAs($user)->get('/verifikasi')->assertRedirect('/daftar/peran');
    }

    public function test_pencari_kerja_belum_terverifikasi_melihat_form_nomor(): void
    {
        $this->actingAs($this->pencariKerja())->get('/verifikasi')
            ->assertOk()
            ->assertSee('Verifikasi nomor WhatsApp')
            ->assertSee('Kirim kode');
    }

    public function test_pemberi_kerja_juga_bisa_membuka_halaman_verifikasi(): void
    {
        $this->actingAs(User::factory()->employer()->create())->get('/verifikasi')
            ->assertOk()
            ->assertSee('Kirim kode');
    }

    public function test_pengguna_terverifikasi_diarahkan_ke_beranda(): void
    {
        $user = $this->denganStatus($this->pencariKerja(), VerificationStatus::Verified);

        $this->actingAs($user)->get('/verifikasi')->assertRedirect('/');
    }

    public function test_pengguna_menunggu_pemeriksaan_tidak_melihat_form_otp(): void
    {
        $user = $this->denganStatus($this->pencariKerja(), VerificationStatus::Pending);

        $this->actingAs($user)->get('/verifikasi')
            ->assertOk()
            ->assertSee('sedang diperiksa')
            ->assertDontSee('Kirim kode');
    }

    // ---------- Kirim OTP ----------

    public function test_kirim_otp_berhasil_menyimpan_nomor_di_session_dan_mengirim_kode(): void
    {
        $user = $this->pencariKerja();

        $this->actingAs($user)->post(route('verifikasi.otp.kirim'), ['nomor' => '0812-3456-7890'])
            ->assertRedirectToRoute('verifikasi.show')
            ->assertSessionHas('verifikasi.nomor', self::NOMOR_BAKU)
            ->assertSessionHas('status');

        $this->assertCount(1, $this->pengirim->terkirim);
        $this->assertDatabaseHas('otp_requests', ['user_id' => $user->id, 'phone_number' => self::NOMOR_BAKU]);
    }

    public function test_nomor_tidak_valid_atau_kosong_ditolak_tanpa_mengirim(): void
    {
        $this->actingAs($this->pencariKerja());

        $this->post(route('verifikasi.otp.kirim'), ['nomor' => '021555123'])->assertSessionHasErrors('nomor');
        $this->post(route('verifikasi.otp.kirim'), [])->assertSessionHasErrors('nomor');

        $this->assertDatabaseCount('otp_requests', 0);
        $this->assertSame([], $this->pengirim->terkirim);
    }

    public function test_permintaan_kedua_sebelum_60_detik_menampilkan_pesan_tunggu(): void
    {
        $this->actingAs($this->pencariKerja());

        $this->post(route('verifikasi.otp.kirim'), ['nomor' => self::NOMOR]);
        $this->post(route('verifikasi.otp.kirim'), ['nomor' => self::NOMOR])
            ->assertSessionHasErrors(['otp' => 'Tunggu 60 detik sebelum meminta kode lagi.']);

        $this->assertCount(1, $this->pengirim->terkirim);                      // yang kedua tidak terkirim
    }

    // ---------- Cek OTP ----------

    public function test_kode_benar_memverifikasi_nomor(): void
    {
        $user = $this->pencariKerja();
        $kode = $this->mintaKode($user);

        $this->post(route('verifikasi.otp.cek'), ['nomor' => self::NOMOR, 'kode' => $kode])
            ->assertRedirectToRoute('verifikasi.show')
            ->assertSessionHas('status', 'Nomor WhatsApp berhasil diverifikasi.')
            ->assertSessionMissing('verifikasi.nomor');                         // tidak lagi menunggu kode

        $this->assertNotNull(OtpRequest::first()->verified_at);
    }

    public function test_kode_salah_menampilkan_pesan_dan_mencatat_percobaan(): void
    {
        $kode = $this->mintaKode($this->pencariKerja());
        $salah = $kode === '000000' ? '111111' : '000000';

        $this->post(route('verifikasi.otp.cek'), ['nomor' => self::NOMOR, 'kode' => $salah])
            ->assertSessionHasErrors(['otp' => 'Kode salah. Sisa percobaan: 4.']);

        $this->assertSame(1, OtpRequest::first()->attempts);
        $this->assertNull(OtpRequest::first()->verified_at);
    }

    public function test_kode_yang_bukan_6_angka_ditolak_tanpa_mengurangi_percobaan(): void
    {
        $this->mintaKode($this->pencariKerja());

        $this->post(route('verifikasi.otp.cek'), ['nomor' => self::NOMOR, 'kode' => '12345'])->assertSessionHasErrors('kode');
        $this->post(route('verifikasi.otp.cek'), ['nomor' => self::NOMOR, 'kode' => 'abcdef'])->assertSessionHasErrors('kode');

        $this->assertSame(0, OtpRequest::first()->attempts);                    // format salah tidak memakan jatah
    }

    public function test_kode_dengan_spasi_hasil_salin_tempel_tetap_diterima(): void
    {
        $kode = $this->mintaKode($this->pencariKerja());
        $denganSpasi = substr($kode, 0, 3).' '.substr($kode, 3);

        $this->post(route('verifikasi.otp.cek'), ['nomor' => self::NOMOR, 'kode' => $denganSpasi])
            ->assertSessionHas('status', 'Nomor WhatsApp berhasil diverifikasi.');
    }

    // ---------- Tampilan mengikuti keadaan ----------

    public function test_halaman_menampilkan_kolom_kode_saat_ada_nomor_aktif_dan_bisa_ganti_nomor(): void
    {
        $user = $this->pencariKerja();

        $this->actingAs($user)->withSession(['verifikasi.nomor' => self::NOMOR_BAKU])->get('/verifikasi')
            ->assertOk()
            ->assertSee('Masukkan kode')
            ->assertSee(self::NOMOR_BAKU)
            ->assertDontSee('Kirim kode');

        $this->actingAs($user)->withSession(['verifikasi.nomor' => self::NOMOR_BAKU])->get('/verifikasi?ganti=1')
            ->assertOk()
            ->assertSee('Kirim kode')
            ->assertSessionMissing('verifikasi.nomor');
    }

    public function test_setelah_berhasil_halaman_menampilkan_nomor_terverifikasi(): void
    {
        $user = $this->pencariKerja();
        $kode = $this->mintaKode($user);
        $this->post(route('verifikasi.otp.cek'), ['nomor' => self::NOMOR, 'kode' => $kode]);

        $this->get('/verifikasi')
            ->assertOk()
            ->assertSee(self::NOMOR_BAKU)
            ->assertSee('sudah terverifikasi')
            ->assertDontSee('Kirim kode');
    }
}