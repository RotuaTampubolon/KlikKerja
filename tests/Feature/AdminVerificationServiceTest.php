<?php

namespace Tests\Feature;

use App\Enums\AdminTaskStatus;
use App\Enums\AdminTaskType;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\AdminTask;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Verification;
use App\Services\AdminTaskQueue;
use App\Services\Verification\AdminVerificationService;
use App\Services\Verification\NomorWhatsAppSudahDipakaiException;
use App\Services\Verification\VerificationNotifier;
use App\Services\Verification\VerificationSudahDiputuskanException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeVerificationNotifier;
use Tests\TestCase;

// Test logika bisnis persetujuan dan penolakan verifikasi oleh Admin.
class AdminVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '6281234567890';

    private FakeVerificationNotifier $pemberitahu;

    protected function setUp(): void
    {
        parent::setUp();

        // Pemberi tahu palsu: pesan disimpan di memori, tidak menyentuh log sungguhan.
        $this->pemberitahu = new FakeVerificationNotifier();
        $this->app->instance(VerificationNotifier::class, $this->pemberitahu);
    }

    // Membuat data uji: Admin, user berstatus Menunggu, pengajuannya, dan kartu antreannya
    // (seperti yang dihasilkan VerificationService di Tahap 3).
    private function siapkan(): array
    {
        $admin = Admin::create(['name' => 'Admin Uji', 'email' => 'admin@example.test', 'password' => 'rahasia123']);
        $user = User::factory()->seeker()->create();

        $v = new Verification(['phone_number' => self::NOMOR, 'ktp_path' => 'ktp/a.jpg', 'selfie_path' => 'selfie/a.jpg']);
        $v->user_id = $user->id;
        $v->save(); // status otomatis 'pending'

        $user->verification_status = VerificationStatus::Pending;
        $user->save();

        AdminTaskQueue::push(AdminTaskType::Verification, 'verification', $v->id);

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

        $v = $v->fresh();
        $this->assertSame(VerificationStatus::Verified, $v->status);
        $this->assertSame($admin->id, $v->reviewed_by);
        $this->assertNotNull($v->reviewed_at);

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

    public function test_setujui_menandai_kartu_antrean_selesai(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->setujui($admin, $v);

        $kartu = AdminTask::first();
        $this->assertSame(AdminTaskStatus::Done, $kartu->status);
        $this->assertSame($admin->id, $kartu->handled_by);
        $this->assertSame('disetujui', $kartu->handling_result);
        $this->assertNotNull($kartu->handled_at);
    }

    public function test_setujui_memberi_tahu_pengguna(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->setujui($admin, $v);

        $this->assertCount(1, $this->pemberitahu->terkirim);
        $this->assertSame($user->id, $this->pemberitahu->terkirim[0]['user_id']);
        $this->assertSame($v->id, $this->pemberitahu->terkirim[0]['verification_id']);
        $this->assertStringContainsString('disetujui', $this->pemberitahu->terkirim[0]['pesan']);
    }

    // ---------- Tolak ----------

    public function test_tolak_mengubah_status_dan_menyimpan_alasan_tanpa_mengisi_nomor_wa(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->tolak($admin, $v, 'Foto KTP tidak terbaca.');

        $v = $v->fresh();
        $this->assertSame(VerificationStatus::Rejected, $v->status);
        $this->assertSame('Foto KTP tidak terbaca.', $v->rejected_reason);
        $this->assertSame($admin->id, $v->reviewed_by);

        $user = $user->fresh();
        $this->assertSame(VerificationStatus::Rejected, $user->verification_status);
        $this->assertNull($user->whatsapp_number); // penolakan bukan pengesahan nomor
    }

    public function test_tolak_mencatat_audit_log_dengan_alasan(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->tolak($admin, $v, 'Selfie tidak menampilkan wajah dengan jelas.');

        $log = AuditLog::where('action', 'verification.rejected')->first();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($v->id, $log->subject_id);
        $this->assertSame(['alasan' => 'Selfie tidak menampilkan wajah dengan jelas.'], $log->meta);
    }

    public function test_tolak_menandai_kartu_selesai_dengan_alasan_dan_memberi_tahu_pengguna(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $this->layanan()->tolak($admin, $v, 'Foto buram.');

        $kartu = AdminTask::first();
        $this->assertSame(AdminTaskStatus::Done, $kartu->status);
        $this->assertSame('ditolak: Foto buram.', $kartu->handling_result);

        $this->assertCount(1, $this->pemberitahu->terkirim);
        $this->assertStringContainsString('Foto buram.', $this->pemberitahu->terkirim[0]['pesan']);
    }

    // ---------- Keputusan tidak boleh diulang ----------

    public function test_setujui_dua_kali_ditolak_dan_tidak_menggandakan_jejak_atau_pemberitahuan(): void
    {
        [$admin, $user, $v] = $this->siapkan();
        $this->layanan()->setujui($admin, $v);

        try {
            $this->layanan()->setujui($admin, $v);
            $this->fail('Harus melempar exception.');
        } catch (VerificationSudahDiputuskanException $e) {
            $this->assertSame(VerificationStatus::Verified, $e->statusSaatIni);
            $this->assertStringContainsString('disetujui', $e->getMessage());
        }

        $this->assertSame(1, AuditLog::where('action', 'verification.approved')->count());
        $this->assertCount(1, $this->pemberitahu->terkirim);
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

    // ---------- Nomor WhatsApp (kolom UNIQUE) ----------

    public function test_nomor_yang_sudah_dipakai_akun_lain_menggagalkan_persetujuan_tanpa_mengubah_apa_pun(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        $pemilikLama = User::factory()->seeker()->create();
        $pemilikLama->whatsapp_number = self::NOMOR; // nomor yang sama sudah resmi milik akun lain
        $pemilikLama->save();

        try {
            $this->layanan()->setujui($admin, $v);
            $this->fail('Harus melempar exception.');
        } catch (NomorWhatsAppSudahDipakaiException $e) {
            $this->assertStringContainsString(self::NOMOR, $e->getMessage());
        }

        // Semuanya utuh: tidak ada yang berubah sedikit pun.
        $this->assertSame(VerificationStatus::Pending, $v->fresh()->status);
        $this->assertSame(VerificationStatus::Pending, $user->fresh()->verification_status);
        $this->assertNull($user->fresh()->whatsapp_number);
        $this->assertSame(AdminTaskStatus::Open, AdminTask::first()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], $this->pemberitahu->terkirim);
    }

    public function test_nomor_yang_sudah_milik_akun_sendiri_tidak_dianggap_bentrok(): void
    {
        [$admin, $user, $v] = $this->siapkan();
        $user->whatsapp_number = self::NOMOR; // mis. pengajuan ulang dengan nomor yang sama
        $user->save();

        $this->layanan()->setujui($admin, $v);

        $this->assertSame(VerificationStatus::Verified, $v->fresh()->status);
    }

    // ---------- Pemberitahuan gagal ----------

    public function test_pemberitahuan_yang_gagal_tidak_membatalkan_keputusan(): void
    {
        [$admin, $user, $v] = $this->siapkan();

        // Pemberi tahu yang selalu gagal (mis. layanan notifikasi sedang mati).
        $this->app->instance(VerificationNotifier::class, new class implements VerificationNotifier {
            public function kirimHasil(User $user, Verification $verification, string $pesan): void
            {
                throw new RuntimeException('Layanan notifikasi mati.');
            }
        });

        $this->layanan()->setujui($admin, $v); // tidak boleh melempar exception

        $this->assertSame(VerificationStatus::Verified, $v->fresh()->status);   // keputusan tetap sah
        $this->assertSame(VerificationStatus::Verified, $user->fresh()->verification_status);
    }
}