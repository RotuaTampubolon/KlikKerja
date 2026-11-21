<?php

namespace Tests\Feature;

use App\Enums\AdminTaskStatus;                       // enum status tugas (buatanmu)
use App\Enums\AdminTaskType;                         // enum jenis tugas (buatanmu)
use App\Enums\VerificationStatus;                    // enum status verifikasi (milik Rotua)
use App\Models\Admin;
use App\Models\AdminTask;
use App\Models\AuditLog;
use App\Models\OtpRequest;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;   // database dibangun ulang tiap test
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;               // untuk memeriksa tabel dan kolom
use Tests\TestCase;                                  // kelas dasar test Laravel

// Test struktur database modul Verifikasi dan Keamanan (Tahap 1).
class VerificationSchemaTest extends TestCase
{
    use RefreshDatabase; // mulai dari database kosong di setiap test

    // ---------- Fungsi bantu (bukan test): membuat data uji agar tidak mengulang kode ----------

    // Membuat satu user bergaya Pencari Kerja memakai state seeker() milik Rotua.
    private function buatUser(): User
    {
        return User::factory()->seeker()->create();
    }

    // Membuat satu Admin. Database test kosong, jadi Admin dari seeder tidak ada di sini.
    private function buatAdmin(string $email = 'admin@example.test'): Admin
    {
        return Admin::create(['name' => 'Admin Uji', 'email' => $email, 'password' => 'rahasia123']);
    }

    // Membuat satu pengajuan verifikasi milik $user. $tambahan untuk kolom yang tidak fillable
    // (mis. reviewed_by), diisi satu per satu seperti yang nanti dilakukan kode server.
    private function buatVerifikasi(User $user, array $tambahan = []): Verification
    {
        $v = new Verification(['phone_number' => '6281234567890', 'ktp_path' => 'ktp/a.jpg', 'selfie_path' => 'selfie/a.jpg']);
        $v->user_id = $user->id;                // user_id sengaja tidak fillable: diisi manual
        foreach ($tambahan as $kolom => $nilai) {
            $v->{$kolom} = $nilai;              // isi kolom tambahan satu per satu
        }
        $v->save();                             // simpan ke database

        return $v;
    }

    // ---------- Struktur tabel ----------

    public function test_semua_tabel_modul_verifikasi_tersedia(): void
    {
        foreach (['verifications', 'otp_requests', 'admin_tasks', 'audit_logs'] as $tabel) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada."); // pesan jika gagal
        }
    }

    public function test_tabel_verifications_punya_kolom_yang_diwajibkan(): void
    {
        $this->assertTrue(Schema::hasColumns('verifications', [
            'user_id', 'phone_number', 'ktp_path', 'selfie_path', 'status',
            'rejected_reason', 'submitted_at', 'reviewed_by', 'reviewed_at',
        ]));
    }

    // ---------- Model Verification ----------

    public function test_pengajuan_baru_berstatus_pending_dan_milik_user(): void
    {
        $user = $this->buatUser();                           // Arrange: siapkan user

        $v = $this->buatVerifikasi($user);                   // Act: buat pengajuan

        $this->assertSame(VerificationStatus::Pending, $v->fresh()->status); // Assert: status awal Pending
        $this->assertTrue($v->user->is($user));                              // Assert: relasi ke user benar
    }

    public function test_path_dokumen_tidak_ikut_keluar_saat_diubah_ke_array(): void
    {
        $v = $this->buatVerifikasi($this->buatUser());

        $array = $v->fresh()->toArray();                     // seperti saat data dikirim sebagai JSON

        $this->assertArrayNotHasKey('ktp_path', $array);     // path KTP tersembunyi
        $this->assertArrayNotHasKey('selfie_path', $array);  // path selfie tersembunyi
    }

    public function test_kolom_keputusan_tidak_bisa_diisi_massal(): void
    {
        // Meniru pengguna jahat yang mencoba menyetujui dirinya sendiri lewat mass assignment.
        $v = new Verification([
            'phone_number' => '6281234567890',
            'ktp_path' => 'ktp/a.jpg',
            'selfie_path' => 'selfie/a.jpg',
            'status' => 'verified',   // harus DIABAIKAN
            'reviewed_by' => 1,       // harus DIABAIKAN
        ]);

        $this->assertSame(VerificationStatus::Pending, $v->status); // status tetap Pending
        $this->assertNull($v->reviewed_by);                          // pemeriksa tetap kosong
    }

    public function test_menghapus_admin_tidak_menghapus_riwayat_pemeriksaan(): void
    {
        $admin = $this->buatAdmin();
        $v = $this->buatVerifikasi($this->buatUser(), ['reviewed_by' => $admin->id]);

        $admin->delete();                                    // akun Admin dihapus

        $this->assertDatabaseHas('verifications', ['id' => $v->id]); // pengajuan MASIH ada
        $this->assertNull($v->fresh()->reviewed_by);                 // pemeriksa jadi kosong (nullOnDelete)
    }

    public function test_menghapus_user_ikut_menghapus_pengajuannya(): void
    {
        $user = $this->buatUser();
        $v = $this->buatVerifikasi($user);

        $user->delete();                                     // pemilik dihapus

        $this->assertDatabaseMissing('verifications', ['id' => $v->id]); // pengajuan ikut hilang (cascade)
    }

    // ---------- Model OtpRequest ----------

    public function test_kode_otp_disimpan_sebagai_hash_dan_disembunyikan(): void
    {
        $user = $this->buatUser();
        $otp = new OtpRequest([
            'phone_number' => '6281234567890',
            'code_hash' => Hash::make('123456'),             // yang disimpan hash-nya, bukan '123456'
            'expires_at' => now()->addMinutes(5),
        ]);
        $otp->user_id = $user->id;                           // user_id sengaja tidak fillable
        $otp->save();

        $segar = $otp->fresh();                              // muat ulang agar default database terlihat

        $this->assertNotSame('123456', $segar->code_hash);                // bukan kode aslinya
        $this->assertTrue(Hash::check('123456', $segar->code_hash));      // kode benar cocok dengan hash
        $this->assertFalse(Hash::check('654321', $segar->code_hash));     // kode salah tidak cocok
        $this->assertArrayNotHasKey('code_hash', $segar->toArray());      // hash tidak ikut ke JSON
        $this->assertSame(0, $segar->attempts);                           // percobaan awal 0
        $this->assertNull($segar->verified_at);                           // belum pernah berhasil
    }

    // ---------- Model AdminTask ----------

    public function test_tugas_admin_baru_berstatus_open_dan_memakai_enum(): void
    {
        $task = new AdminTask(['type' => AdminTaskType::Verification, 'ref_type' => 'verification', 'ref_id' => 12]);
        $task->save();

        $segar = $task->fresh();

        $this->assertSame(AdminTaskType::Verification, $segar->type);   // keluar sebagai objek enum
        $this->assertSame(AdminTaskStatus::Open, $segar->status);       // tugas baru selalu Open
    }

    public function test_status_tugas_admin_tidak_bisa_diselundupkan(): void
    {
        $task = new AdminTask(['type' => AdminTaskType::Report, 'ref_type' => 'report', 'ref_id' => 5, 'status' => 'done']);

        $this->assertSame(AdminTaskStatus::Open, $task->status);        // nilai 'done' dibuang
    }

    // ---------- Model AuditLog ----------

    public function test_audit_log_mencatat_pelaku_admin_dan_objek_verifikasi(): void
    {
        $admin = $this->buatAdmin();
        $v = $this->buatVerifikasi($this->buatUser());

        $log = new AuditLog(['action' => 'verification.approved', 'meta' => ['catatan' => 'KTP jelas'], 'ip_address' => '127.0.0.1']);
        $log->actor()->associate($admin);                    // pelaku = Admin (relasi polimorfik)
        $log->subject()->associate($v);                      // objek = pengajuan verifikasi
        $log->save();

        $segar = $log->fresh();

        $this->assertSame(Admin::class, $segar->actor_type);                  // jenis pelaku tersimpan
        $this->assertTrue($segar->actor->is($admin));                         // relasi pelaku benar
        $this->assertTrue($segar->subject->is($v));                           // relasi objek benar
        $this->assertSame(['catatan' => 'KTP jelas'], $segar->meta);          // JSON kembali jadi array
        $this->assertNotNull($segar->created_at);                             // waktu tercatat otomatis
        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));    // log tidak bisa "diperbarui"
    }

    public function test_audit_log_boleh_tanpa_pelaku_untuk_tindakan_sistem(): void
    {
        $log = new AuditLog(['action' => 'system.cleanup']);
        $log->save();

        $this->assertNull($log->fresh()->actor);             // tidak ada pelaku = tindakan sistem
    }

    // ---------- Disk privat ----------

    public function test_disk_private_tertutup_untuk_umum(): void
    {
        $disk = config('filesystems.disks.private');

        $this->assertFalse($disk['serve']);                  // tidak ada route penyaji file
        $this->assertTrue($disk['throw']);                   // gagal simpan = error
        $this->assertSame('private', $disk['visibility']);   // izin file tertutup
        $this->assertStringNotContainsString('app'.DIRECTORY_SEPARATOR.'public', $disk['root']); // bukan folder publik
        $this->assertArrayNotHasKey('url', $disk);           // tanpa url = tidak punya alamat web
    }
}