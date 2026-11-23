<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\OtpRequest;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

// Test unggah KTP dan selfie: validasi file, syarat OTP, penyimpanan privat, status, dan antrean Admin.
class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR_BAKU = '6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->freezeTime();
        Storage::fake('private');   // disk privat PALSU di folder sementara: file asli tidak tersentuh
        Storage::fake('public');    // untuk membuktikan tidak ada file yang bocor ke disk publik
    }

    private function pencariKerja(): User
    {
        return User::factory()->seeker()->create();
    }

    // Membuat OTP yang SUDAH terverifikasi (syarat unggah dokumen). Langsung ke database agar test fokus.
    private function denganOtpSelesai(User $user): User
    {
        $otp = new OtpRequest(['phone_number' => self::NOMOR_BAKU, 'code_hash' => 'x', 'expires_at' => now()->addMinutes(5)]);
        $otp->user_id = $user->id;
        $otp->forceFill(['verified_at' => now()])->save();

        return $user;
    }

    private function denganStatus(User $user, VerificationStatus $status): User
    {
        $user->verification_status = $status;
        $user->save();

        return $user;
    }

    // Gambar palsu. image() membuat gambar sungguhan (butuh ekstensi GD), size() mengatur ukuran yang dilaporkan (KB).
    private function foto(string $nama, int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->image($nama)->size($kb);
    }

    // Mengirim form unggah sebagai $user. $ganti menimpa isian bawaan.
    private function kirim(User $user, array $ganti = [])
    {
        return $this->actingAs($user)->post(route('verifikasi.dokumen.store'), array_merge([
            'ktp' => $this->foto('ktp-asli.jpg'),
            'selfie' => $this->foto('selfie-asli.png'),
        ], $ganti));
    }

    // ---------- Akses dan syarat ----------

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->post(route('verifikasi.dokumen.store'), [])->assertRedirectToRoute('login');
    }

    public function test_tanpa_otp_selesai_ditolak_dan_tidak_menyimpan_file(): void
    {
        $user = $this->pencariKerja(); // belum verifikasi OTP

        $this->kirim($user)->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('verifications', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertSame(VerificationStatus::Unverified, $user->fresh()->verification_status);
    }

    // ---------- Berhasil ----------

    public function test_unggah_berhasil_menyimpan_privat_mengubah_status_dan_masuk_antrean(): void
    {
        $user = $this->denganOtpSelesai($this->pencariKerja());

        $this->kirim($user)
            ->assertRedirectToRoute('verifikasi.show')
            ->assertSessionHas('status');

        $v = Verification::first();

        // Pengajuan tercatat atas nama user, dengan nomor yang lolos OTP, berstatus menunggu.
        $this->assertSame($user->id, $v->user_id);
        $this->assertSame(self::NOMOR_BAKU, $v->phone_number);
        $this->assertSame(VerificationStatus::Pending, $v->status);

        // File ada di disk PRIVAT, di folder yang benar.
        $this->assertStringStartsWith('ktp/', $v->ktp_path);
        $this->assertStringStartsWith('selfie/', $v->selfie_path);
        $this->assertTrue(Storage::disk('private')->exists($v->ktp_path));
        $this->assertTrue(Storage::disk('private')->exists($v->selfie_path));

        // Nama file diacak: nama asli dari pengguna tidak dipakai.
        $this->assertNotSame('ktp-asli.jpg', basename($v->ktp_path));
        $this->assertNotSame('selfie-asli.png', basename($v->selfie_path));

        // Tidak ada yang bocor ke disk publik.
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Status pengguna berubah, masuk antrean Admin, dan tercatat di jejak aktivitas.
        $this->assertSame(VerificationStatus::Pending, $user->fresh()->verification_status);
        $this->assertDatabaseHas('admin_tasks', ['type' => 'verification', 'ref_type' => 'verification', 'ref_id' => $v->id, 'status' => 'open']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'verification.submitted', 'subject_id' => $v->id, 'actor_id' => $user->id]);
    }

    public function test_pemberi_kerja_juga_bisa_mengunggah(): void
    {
        $user = $this->denganOtpSelesai(User::factory()->employer()->create());

        $this->kirim($user)->assertSessionHas('status');

        $this->assertSame(1, Verification::count());
    }

    // ---------- Validasi file ----------

    public function test_file_wajib_ada(): void
    {
        $user = $this->denganOtpSelesai($this->pencariKerja());

        $this->actingAs($user)->post(route('verifikasi.dokumen.store'), [])
            ->assertSessionHasErrors(['ktp', 'selfie']);

        $this->assertDatabaseCount('verifications', 0);
    }

    public function test_format_selain_jpg_jpeg_png_ditolak(): void
    {
        $user = $this->denganOtpSelesai($this->pencariKerja());

        $this->kirim($user, ['ktp' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['ktp' => 'Foto KTP harus berformat JPG, JPEG, atau PNG.']);

        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_batas_ukuran_tepat_4_mb(): void
    {
        $user = $this->denganOtpSelesai($this->pencariKerja());

        // 4097 KB (lebih dari 4 MB) ditolak.
        $this->kirim($user, ['selfie' => $this->foto('selfie.jpg', 4097)])
            ->assertSessionHasErrors(['selfie' => 'Ukuran foto selfie maksimal 4 MB.']);
        $this->assertDatabaseCount('verifications', 0);

        // 4096 KB (tepat 4 MB) diterima.
        $this->kirim($user, ['ktp' => $this->foto('ktp.jpg', 4096), 'selfie' => $this->foto('selfie.jpg', 4096)])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('verifications', 1);
    }

    // ---------- Status akun ----------

    public function test_yang_sedang_diperiksa_tidak_bisa_mengirim_lagi(): void
    {
        $user = $this->denganStatus($this->denganOtpSelesai($this->pencariKerja()), VerificationStatus::Pending);

        $this->kirim($user)->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('verifications', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_yang_sudah_terverifikasi_tidak_bisa_mengirim_lagi(): void
    {
        $user = $this->denganStatus($this->denganOtpSelesai($this->pencariKerja()), VerificationStatus::Verified);

        $this->kirim($user)->assertSessionHasErrors('dokumen');

        $this->assertDatabaseCount('verifications', 0);
    }

    public function test_yang_ditolak_boleh_mengirim_ulang_dan_riwayat_tetap_ada(): void
    {
        $user = $this->denganStatus($this->denganOtpSelesai($this->pencariKerja()), VerificationStatus::Rejected);

        // Pengajuan lama yang ditolak.
        $lama = new Verification(['phone_number' => self::NOMOR_BAKU, 'ktp_path' => 'ktp/lama.jpg', 'selfie_path' => 'selfie/lama.jpg']);
        $lama->user_id = $user->id;
        $lama->forceFill(['status' => VerificationStatus::Rejected, 'rejected_reason' => 'Foto KTP tidak terbaca.'])->save();

        $this->kirim($user)->assertSessionHas('status');

        $this->assertSame(2, Verification::count());                                         // riwayat + yang baru
        $this->assertSame(VerificationStatus::Rejected, $lama->fresh()->status);             // yang lama tidak berubah
        $this->assertSame(VerificationStatus::Pending, Verification::latest('id')->first()->status);
        $this->assertSame(VerificationStatus::Pending, $user->fresh()->verification_status);
    }

    // ---------- Kegagalan di tengah jalan ----------

    public function test_jika_penyimpanan_gagal_file_dibersihkan_dan_status_tidak_berubah(): void
    {
        $user = $this->denganOtpSelesai($this->pencariKerja());

        // Memaksa kegagalan SETELAH kedua file sudah ditulis, tepat saat baris pengajuan dibuat.
        Verification::creating(fn() => throw new RuntimeException('simulasi gagal'));
        $this->withoutExceptionHandling();

        try {
            $this->kirim($user);
            $this->fail('Seharusnya melempar RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulasi gagal', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('private')->allFiles());                         // tidak ada foto KTP yatim
        $this->assertDatabaseCount('verifications', 0);
        $this->assertDatabaseCount('admin_tasks', 0);
        $this->assertSame(VerificationStatus::Unverified, $user->fresh()->verification_status);
    }

    // ---------- Tampilan ----------

    public function test_halaman_menampilkan_form_unggah_setelah_otp_selesai(): void
    {
        $user = $this->denganOtpSelesai($this->pencariKerja());

        $this->actingAs($user)->get('/verifikasi')
            ->assertOk()
            ->assertSee('Unggah dokumen identitas')
            ->assertSee('Kirim dokumen')
            ->assertSee('hanya dipakai untuk memeriksa identitas');
    }

    public function test_halaman_menampilkan_alasan_penolakan_terakhir(): void
    {
        $user = $this->denganStatus($this->denganOtpSelesai($this->pencariKerja()), VerificationStatus::Rejected);
        $v = new Verification(['phone_number' => self::NOMOR_BAKU, 'ktp_path' => 'ktp/a.jpg', 'selfie_path' => 'selfie/a.jpg']);
        $v->user_id = $user->id;
        $v->forceFill(['status' => VerificationStatus::Rejected, 'rejected_reason' => 'Foto KTP tidak terbaca.'])->save();

        $this->actingAs($user)->get('/verifikasi')
            ->assertOk()
            ->assertSee('Verifikasi sebelumnya ditolak')
            ->assertSee('Foto KTP tidak terbaca.')
            ->assertSee('Kirim dokumen');
    }
}
