<?php

namespace Tests\Feature;

use App\Enums\AdminTaskStatus;
use App\Enums\AdminTaskType;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\AdminTask;
use App\Models\User;
use App\Models\Verification;
use App\Services\AdminTaskQueue;
use App\Services\Verification\VerificationNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakeVerificationNotifier;
use Tests\TestCase;

// Test halaman Admin: antrean, detail, dan tombol setujui/tolak, termasuk siapa yang TIDAK boleh mengaksesnya.
class AdminVerificationReviewTest extends TestCase
{
    use RefreshDatabase;

    private FakeVerificationNotifier $pemberitahu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->pemberitahu = new FakeVerificationNotifier();
        $this->app->instance(VerificationNotifier::class, $this->pemberitahu);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Admin Uji', 'email' => 'admin@example.test', 'password' => 'rahasia123']);
    }

    // Membuat satu pengajuan lengkap dengan pemiliknya. Pengajuan yang masih Menunggu otomatis
    // punya kartu antrean, seperti hasil VerificationService di Tahap 3.
    private function pengajuan(string $nama, VerificationStatus $status = VerificationStatus::Pending, ?Carbon $dikirim = null): Verification
    {
        $user = User::factory()->seeker()->create(['name' => $nama]);
        $user->verification_status = $status;
        $user->save();

        $v = new Verification(['phone_number' => '6281234567890', 'ktp_path' => 'ktp/a.jpg', 'selfie_path' => 'selfie/a.jpg']);
        $v->user_id = $user->id;
        $v->forceFill(['status' => $status, 'submitted_at' => $dikirim ?? now()])->save();

        if ($status === VerificationStatus::Pending) {
            AdminTaskQueue::push(AdminTaskType::Verification, 'verification', $v->id);
        }

        return $v;
    }

    // ---------- Akses ----------

    public function test_tamu_diarahkan_ke_login_admin(): void
    {
        $this->get('/admin/verifikasi')->assertRedirectToRoute('admin.login');
    }

    public function test_pengguna_biasa_tidak_bisa_membuka_antrean_admin(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/admin/verifikasi')->assertRedirectToRoute('admin.login');
    }

    public function test_pengguna_biasa_tidak_bisa_menyetujui_pengajuannya_sendiri(): void
    {
        $v = $this->pengajuan('Pemohon Nakal');

        // Pemilik pengajuan mencoba menyetujui dirinya sendiri dengan mengirim POST langsung.
        $this->actingAs($v->user)->post(route('admin.verifikasi.setujui', $v))->assertRedirectToRoute('admin.login');

        $this->assertSame(VerificationStatus::Pending, $v->fresh()->status);
        $this->assertSame(VerificationStatus::Pending, $v->user->fresh()->verification_status);
        $this->assertSame([], $this->pemberitahu->terkirim);
    }

    // ---------- Antrean ----------

    public function test_antrean_bawaan_hanya_menampilkan_yang_menunggu_dengan_yang_terlama_di_atas(): void
    {
        $this->pengajuan('Pemohon Kedua', dikirim: now()->subHour());
        $this->pengajuan('Pemohon Pertama', dikirim: now()->subHours(3));
        $this->pengajuan('Sudah Disetujui', VerificationStatus::Verified);

        $this->actingAs($this->admin(), 'admin')->get(route('admin.verifikasi.index'))
            ->assertOk()
            ->assertSeeInOrder(['Pemohon Pertama', 'Pemohon Kedua'])   // terlama lebih dulu
            ->assertDontSee('Sudah Disetujui');
    }

    public function test_label_tab_menampilkan_jumlah_per_status(): void
    {
        $this->pengajuan('A');
        $this->pengajuan('B');
        $this->pengajuan('C', VerificationStatus::Verified);
        $this->pengajuan('D', VerificationStatus::Rejected);

        $this->actingAs($this->admin(), 'admin')->get(route('admin.verifikasi.index'))
            ->assertSee('Menunggu (2)')
            ->assertSee('Disetujui (1)')
            ->assertSee('Ditolak (1)')
            ->assertSee('Semua (4)');
    }

    public function test_filter_status_bekerja_dan_nilai_asing_kembali_ke_menunggu(): void
    {
        $this->pengajuan('Masih Menunggu');
        $this->pengajuan('Sudah Disetujui', VerificationStatus::Verified);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get(route('admin.verifikasi.index', ['status' => 'verified']))
            ->assertSee('Sudah Disetujui')->assertDontSee('Masih Menunggu');

        $this->actingAs($admin, 'admin')->get(route('admin.verifikasi.index', ['status' => 'semua']))
            ->assertSee('Sudah Disetujui')->assertSee('Masih Menunggu');

        $this->actingAs($admin, 'admin')->get(route('admin.verifikasi.index', ['status' => 'ngawur']))
            ->assertSee('Masih Menunggu')->assertDontSee('Sudah Disetujui');
    }

    public function test_antrean_kosong_menampilkan_pesan_ramah(): void
    {
        $this->actingAs($this->admin(), 'admin')->get(route('admin.verifikasi.index'))
            ->assertOk()
            ->assertSee('Tidak ada pengajuan pada kategori ini.');
    }

    // ---------- Detail ----------

    public function test_detail_menampilkan_data_gambar_dari_endpoint_admin_dan_tombol_keputusan(): void
    {
        $v = $this->pengajuan('Budi Santoso');

        $this->actingAs($this->admin(), 'admin')->get(route('admin.verifikasi.show', $v))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('6281234567890')
            ->assertSee(route('verifikasi.dokumen.show', ['verification' => $v->id, 'type' => 'ktp']))
            ->assertSee(route('verifikasi.dokumen.show', ['verification' => $v->id, 'type' => 'selfie']))
            ->assertSee('Setujui verifikasi')
            ->assertSee('Tolak verifikasi');
    }

    public function test_detail_yang_sudah_ditolak_menampilkan_alasan_tanpa_tombol(): void
    {
        $admin = $this->admin();
        $v = $this->pengajuan('Sudah Ditolak', VerificationStatus::Rejected);
        $v->forceFill(['rejected_reason' => 'Foto KTP tidak terbaca.', 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();

        $this->actingAs($admin, 'admin')->get(route('admin.verifikasi.show', $v))
            ->assertOk()
            ->assertSee('sudah ditolak')
            ->assertSee('Foto KTP tidak terbaca.')
            ->assertDontSee('Setujui verifikasi')
            ->assertDontSee('Tolak verifikasi');
    }

    public function test_detail_pengajuan_yang_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/verifikasi/999')->assertNotFound();
    }

    // ---------- Keputusan ----------

    public function test_setujui_mengubah_semuanya_dan_kembali_ke_antrean(): void
    {
        $v = $this->pengajuan('Pemohon');

        $this->actingAs($this->admin(), 'admin')->post(route('admin.verifikasi.setujui', $v))
            ->assertRedirectToRoute('admin.verifikasi.index')
            ->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Verified, $v->fresh()->status);
        $this->assertSame(VerificationStatus::Verified, $v->user->fresh()->verification_status);
        $this->assertSame('6281234567890', $v->user->fresh()->whatsapp_number);
        $this->assertSame(AdminTaskStatus::Done, AdminTask::first()->status);
        $this->assertCount(1, $this->pemberitahu->terkirim);
    }

    public function test_tolak_dengan_alasan_menyimpan_alasan_dan_memberi_tahu_pengguna(): void
    {
        $v = $this->pengajuan('Pemohon');

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.verifikasi.tolak', $v), ['alasan' => 'Foto KTP buram, NIK tidak terbaca.'])
            ->assertRedirectToRoute('admin.verifikasi.index')
            ->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Rejected, $v->fresh()->status);
        $this->assertSame('Foto KTP buram, NIK tidak terbaca.', $v->fresh()->rejected_reason);
        $this->assertSame(VerificationStatus::Rejected, $v->user->fresh()->verification_status);
        $this->assertStringContainsString('NIK tidak terbaca', $this->pemberitahu->terkirim[0]['pesan']);
    }

    public function test_tolak_tanpa_alasan_atau_alasan_terlalu_pendek_ditolak(): void
    {
        $v = $this->pengajuan('Pemohon');
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.verifikasi.tolak', $v), [])->assertSessionHasErrors('alasan');
        $this->post(route('admin.verifikasi.tolak', $v), ['alasan' => 'tidak'])->assertSessionHasErrors('alasan');

        $this->assertSame(VerificationStatus::Pending, $v->fresh()->status);   // tidak ada yang berubah
        $this->assertSame([], $this->pemberitahu->terkirim);
    }

    public function test_menyetujui_dua_kali_menampilkan_pesan_dan_tidak_memberi_tahu_ulang(): void
    {
        $v = $this->pengajuan('Pemohon');
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.verifikasi.setujui', $v));
        $this->post(route('admin.verifikasi.setujui', $v))
            ->assertRedirectToRoute('admin.verifikasi.show', $v)
            ->assertSessionHasErrors(['keputusan' => 'Pengajuan ini sudah disetujui sebelumnya.']);

        $this->assertCount(1, $this->pemberitahu->terkirim);
    }

    public function test_nomor_yang_dipakai_akun_lain_menampilkan_pesan_dan_tidak_mengubah_apa_pun(): void
    {
        $v = $this->pengajuan('Pemohon');
        $pemilikLama = User::factory()->seeker()->create();
        $pemilikLama->whatsapp_number = '6281234567890';
        $pemilikLama->save();

        $this->actingAs($this->admin(), 'admin')->post(route('admin.verifikasi.setujui', $v))
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(VerificationStatus::Pending, $v->fresh()->status);
        $this->assertSame([], $this->pemberitahu->terkirim);
    }
}
