<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Test pembukaan dokumen identitas: hanya Admin, tidak lewat URL publik, dan tercatat di audit log.
class DocumentViewTest extends TestCase
{
    use RefreshDatabase;

    // Isi sebuah gambar PNG 1x1 piksel yang VALID. Dipakai agar jenis file terdeteksi
    // sungguhan sebagai image/png, bukan sekadar menurut nama filenya.
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();        // tidak butuh build Vite (seperti test Rotua)
        Storage::fake('private');    // disk privat PALSU di folder sementara: file asli tidak tersentuh
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    // Membuat satu akun Admin. Database test kosong, jadi Admin dari seeder tidak ada di sini.
    private function admin(): Admin
    {
        return Admin::create(['name' => 'Admin Uji', 'email' => 'admin@example.test', 'password' => 'rahasia123']);
    }

    // Membuat satu pengajuan verifikasi lengkap dengan file KTP dan selfie di disk privat (palsu).
    // File selfie sengaja DIBEDAKAN isinya (ditambah kata "selfie") agar test bisa memastikan
    // bahwa type=selfie benar-benar mengembalikan file selfie, bukan file KTP.
    private function pengajuan(?User $pemilik = null): Verification
    {
        $pemilik ??= User::factory()->seeker()->create(); // ??= : pakai pemilik baru jika tidak diberikan

        Storage::disk('private')->put('ktp/ktp-uji.png', $this->png);
        Storage::disk('private')->put('selfie/selfie-uji.png', $this->png.'selfie');

        $v = new Verification(['phone_number' => '6281234567890', 'ktp_path' => 'ktp/ktp-uji.png', 'selfie_path' => 'selfie/selfie-uji.png']);
        $v->user_id = $pemilik->id; // user_id sengaja tidak fillable: diisi manual
        $v->save();

        return $v;
    }

    // Membuat alamat dokumen dari nama route, supaya test tidak bergantung pada tulisan URL-nya.
    private function url(Verification $v, string $type): string
    {
        return route('verifikasi.dokumen.show', ['verification' => $v->id, 'type' => $type]);
    }

    // ---------- Siapa yang TIDAK boleh ----------

    public function test_tamu_tidak_bisa_membuka_dokumen(): void
    {
        $v = $this->pengajuan();

        // Belum login sama sekali: dialihkan ke halaman masuk, bukan menerima file.
        $this->get($this->url($v, 'ktp'))->assertRedirectToRoute('login');
    }

    public function test_pemilik_dokumen_sendiri_tidak_bisa_membukanya_lewat_url(): void
    {
        $pemilik = User::factory()->seeker()->create();
        $v = $this->pengajuan($pemilik);

        // Pemilik login sebagai pengguna biasa (guard web), tetapi tetap TIDAK boleh.
        // Dokumen identitas hanya untuk Admin (prinsip least privilege, PRD 12.2).
        $this->actingAs($pemilik)->get($this->url($v, 'ktp'))->assertRedirectToRoute('login');
    }

    public function test_pengguna_lain_tidak_bisa_membuka_dokumen_orang(): void
    {
        $v = $this->pengajuan();
        $orangLain = User::factory()->seeker()->create();

        $this->actingAs($orangLain)->get($this->url($v, 'selfie'))->assertRedirectToRoute('login');
    }

    // ---------- Admin ----------

    public function test_admin_bisa_membuka_ktp_dengan_header_aman(): void
    {
        $v = $this->pengajuan();

        // actingAs($admin, 'admin') = login sebagai Admin pada guard 'admin'.
        $respons = $this->actingAs($this->admin(), 'admin')->get($this->url($v, 'ktp'));

        $respons->assertOk();                                                     // status 200
        $respons->assertHeader('Content-Type', 'image/png');                      // jenis file terdeteksi benar
        $respons->assertHeader('X-Content-Type-Options', 'nosniff');              // browser dilarang menebak jenis file
        $this->assertStringContainsString('no-store', $respons->headers->get('Cache-Control')); // tidak boleh di-cache
        $this->assertSame($this->png, $respons->streamedContent());               // isi yang diterima sama persis dengan aslinya
    }

    public function test_admin_mendapat_selfie_untuk_type_selfie(): void
    {
        $v = $this->pengajuan();

        $respons = $this->actingAs($this->admin(), 'admin')->get($this->url($v, 'selfie'));

        $this->assertSame($this->png.'selfie', $respons->streamedContent());      // file selfie, bukan file KTP
    }

    public function test_setiap_pembukaan_dokumen_tercatat_di_audit_log(): void
    {
        $v = $this->pengajuan();
        $admin = $this->admin();

        // withServerVariables mengatur alamat IP yang tampak bagi aplikasi pada request berikutnya.
        $this->actingAs($admin, 'admin')
            ->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->get($this->url($v, 'ktp'))
            ->assertOk();

        $log = AuditLog::first();
        $this->assertSame('verification.document_viewed', $log->action);   // jenis tindakan
        $this->assertSame(Admin::class, $log->actor_type);                 // pelakunya seorang Admin
        $this->assertSame($admin->id, $log->actor_id);                     // Admin yang mana
        $this->assertSame($v->id, $log->subject_id);                       // pengajuan yang mana
        $this->assertSame(['type' => 'ktp'], $log->meta);                  // dokumen jenis apa
        $this->assertSame('10.1.2.3', $log->ip_address);                   // dari alamat mana
    }

    // ---------- Kasus tidak ada ----------

    public function test_type_selain_ktp_dan_selfie_menghasilkan_404(): void
    {
        $v = $this->pengajuan();

        // "paspor" tidak lolos whereIn di route, jadi route tidak cocok sama sekali: 404.
        $this->actingAs($this->admin(), 'admin')->get("/verifikasi/dokumen/{$v->id}/paspor")->assertNotFound();
    }

    public function test_pengajuan_yang_tidak_ada_menghasilkan_404(): void
    {
        // Id 999 tidak ada di database: route model binding membalas 404.
        $this->actingAs($this->admin(), 'admin')->get('/verifikasi/dokumen/999/ktp')->assertNotFound();
    }

    public function test_file_yang_hilang_dari_disk_menghasilkan_404_dan_tidak_tercatat(): void
    {
        $v = $this->pengajuan();
        Storage::disk('private')->delete($v->ktp_path); // barisnya ada, filenya hilang

        $this->actingAs($this->admin(), 'admin')->get($this->url($v, 'ktp'))->assertNotFound();

        // Yang gagal dibuka tidak boleh tercatat sebagai "dibuka".
        $this->assertDatabaseCount('audit_logs', 0);
    }
}