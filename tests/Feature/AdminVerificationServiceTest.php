<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\OtpRequest;
use App\Models\User;
use App\Models\Verification;
use App\Services\Verification\AdminVerificationService;
use App\Services\Verification\VerificationSudahDiputuskanException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Test logika bisnis persetujuan dan penolakan verifikasi oleh Admin.
class AdminVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '6281234567890';

    // Membuat data uji: Admin, user dengan OTP selesai, dan pengajuan verifikasi.
    private function siapkan(): array
    {
        $admin = Admin::create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ]);

        $user = User::factory()->seeker()->create();

        // OTP sudah selesai (syarat pengajuan di Tahap 3).
        $otp = new OtpRequest([
            'phone_number' => self::NOMOR,
            'code_hash' => 'x',
            'expires_at' => now()->addMinutes(5),
        ]);
        $otp->user_id = $user->id;
        $otp->forceFill(['verified_at' => now()])->save();

        // Pengajuan manual (melewati service Tahap 3 agar test fokus pada Tahap 4).
        $v = new Verification([
            'phone_number' => self::NOMOR,
            'ktp_path' => 'ktp/a.jpg',
            'selfie_path' => 'selfie/a.jpg',
        ]);
        $v->user_id = $user->id;
        $v->save(); // status otomatis 'pending'

        // Status akun juga ikut pending seperti yang dihasilkan Tahap 3.
        $user->verification_status = VerificationStatus::Pending;
        $user->save();

        return [$admin, $user, $v];
    }

    private function layanan(): AdminVerificationService
    {
        return app(AdminVerificationService::class);
    }

    // ---------- Setujui ----------

    public function test_setujui_mengubah_status_pengajuan_dan_akun_serta_mengisi_nomor_wa(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->setujui($admin, $v);

        // Status pengajuan berubah dan tercatat siapa yang memutuskan.
        $v = $v->fresh();
        $this->assertSame(VerificationStatus::Verified, $v->status);
        $this->assertSame($admin->id, $v->reviewed_by);
        $this->assertNotNull($v->reviewed_at);

        // Status akun berubah dan nomor WA terisi dari nomor pengajuan.
        $user = $user->fresh();
        $this->assertSame(VerificationStatus::Verified, $user->verification_status);
        $this->assertSame(self::NOMOR, $user->whatsapp_number);
    }

    public function test_setujui_mencatat_audit_log(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->setujui($admin, $v);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'verification.approved',
            'actor_type' => Admin::class,
            'actor_id' => $admin->id,
            'subject_id' => $v->id,
        ]);
    }

    // ---------- Tolak ----------

    public function test_tolak_mengubah_status_dan_menyimpan_alasan(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->tolak($admin, $v, 'Foto KTP tidak terbaca.');

        $v = $v->fresh();
        $this->assertSame(VerificationStatus::Rejected, $v->status);
        $this->assertSame('Foto KTP tidak terbaca.', $v->rejected_reason);
        $this->assertSame($admin->id, $v->reviewed_by);

        // Status akun kembali ke Rejected (pengguna bisa kirim ulang).
        $user = $user->fresh();
        $this->assertSame(VerificationStatus::Rejected, $user->verification_status);

        // whatsapp_number TIDAK diisi karena penolakan bukan persetujuan.
        $this->assertNull($user->whatsapp_number);
    }

    public function test_tolak_mencatat_audit_log_dengan_alasan(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->tolak($admin, $v, 'Selfie tidak menampilkan wajah dengan jelas.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'verification.rejected',
            'actor_id' => $admin->id,
            'subject_id' => $v->id,
        ]);
    }

    // ---------- Pengamanan: keputusan tidak boleh diulang ----------

    public function test_setujui_dua_kali_ditolak(): void
    {
        [$admin, $user, $v] = $this->siapkan();
        $this->layanan()->setujui($admin, $v);

        // Mencoba menyetujui lagi: harus gagal.
        $this->expectException(VerificationSudahDiputuskanException::class);
        $this->layanan()->setujui($admin, $v);
    }

    public function test_tolak_setelah_disetujui_ditolak(): void
    {
        [$admin, $user, $v] = $this->siapkan();
        $this->layanan()->setujui($admin, $v);

        $this->expectException(VerificationSudahDiputuskanException::class);
        $this->layanan()->tolak($admin, $v, 'Mencoba mengubah keputusan.');
    }

    public function test_setujui_setelah_ditolak_juga_ditolak(): void
    {
        [$admin, $user, $v] = $this->siapkan();
        $this->layanan()->tolak($admin, $v, 'Foto buram.');

        $this->expectException(VerificationSudahDiputuskanException::class);
        $this->layanan()->setujui($admin, $v);
    }

    public function test_exception_membawa_status_saat_ini(): void
    {
        [$admin, $user, $v] = $this->siapkan();
        $this->layanan()->setujui($admin, $v);

        try {
            $this->layanan()->setujui($admin, $v);
            $this->fail('Harus melempar exception.');
        } catch (VerificationSudahDiputuskanException $e) {
            // Exception membawa status yang sedang berlaku, dipakai controller untuk pesan yang tepat.
            $this->assertSame(VerificationStatus::Verified, $e->statusSaatIni);
            $this->assertStringContainsString('disetujui', $e->getMessage());
        }
    }
}