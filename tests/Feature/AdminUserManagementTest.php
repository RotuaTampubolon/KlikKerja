<?php

namespace Tests\Feature;

use App\Enums\SanctionType;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ]);
    }

    public function test_tamu_tidak_bisa_membuka_daftar_pengguna(): void
    {
        $this->get('/admin/pengguna')->assertRedirectToRoute('admin.login');
    }

    public function test_pengguna_biasa_tidak_bisa_membuka_daftar_pengguna(): void
    {
        $this->actingAs(User::factory()->seeker()->create())
            ->get('/admin/pengguna')
            ->assertRedirectToRoute('admin.login');
    }

    public function test_admin_melihat_dan_mencari_pengguna(): void
    {
        User::factory()->create(['name' => 'Budi Santoso']);
        User::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/pengguna?q=Budi')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Siti Aminah');
    }

    public function test_peringatan_dicatat_tanpa_membatasi_akun(): void
    {
        $admin = $this->admin();
        $user = User::factory()->seeker()->create();

        $this->actingAs($admin, 'admin')
            ->post("/admin/pengguna/{$user->id}/peringatan", ['reason' => 'Iklan menyesatkan'])
            ->assertRedirectToRoute('admin.users.show', $user);

        $this->assertDatabaseHas('user_sanctions', [
            'user_id' => $user->id,
            'admin_id' => $admin->id, // pelaku tercatat dari Admin yang login
            'type' => 'warning',
            'reason' => 'Iklan menyesatkan',
        ]);
        $this->assertNull($user->currentRestriction());
    }

    public function test_alasan_wajib_diisi(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/peringatan", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('user_sanctions', 0);
    }

    public function test_suspend_menghitung_waktu_berakhir(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/suspend", ['reason' => 'Spam', 'days' => 7])
            ->assertRedirectToRoute('admin.users.show', $user);

        $sanction = $user->sanctions()->first();
        $this->assertSame(SanctionType::Suspension, $sanction->type);
        $this->assertTrue($sanction->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertNotNull($user->currentRestriction());
    }

    public function test_suspend_tanpa_lama_ditolak(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/suspend", ['reason' => 'Spam'])
            ->assertSessionHasErrors('days');

        $this->assertDatabaseCount('user_sanctions', 0);
    }

    public function test_blokir_membatasi_akun(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/blokir", ['reason' => 'Penipuan']);

        $this->assertSame(SanctionType::Ban, $user->currentRestriction()->type);
    }

    public function test_suspend_pada_akun_yang_sudah_diblokir_ditolak(): void
    {
        $user = User::factory()->seeker()->create();
        $user->sanctions()->create(['type' => SanctionType::Ban]);

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/suspend", ['reason' => 'Spam', 'days' => 3])
            ->assertSessionHasErrors('sanction');

        $this->assertDatabaseCount('user_sanctions', 1);
    }

    public function test_pulihkan_membebaskan_akun_dan_menyimpan_riwayat(): void
    {
        $user = User::factory()->seeker()->create();
        $user->sanctions()->create(['type' => SanctionType::Ban]);

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/pulihkan", ['reason' => 'Banding diterima']);

        $this->assertNull($user->currentRestriction());
        $this->assertDatabaseCount('user_sanctions', 2); // blokir lama TETAP ada
    }

    public function test_pulihkan_akun_yang_tidak_dibatasi_ditolak(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/pulihkan", ['reason' => 'Coba-coba'])
            ->assertSessionHasErrors('sanction');

        $this->assertDatabaseCount('user_sanctions', 0);
    }

    public function test_pengguna_yang_diblokir_dikeluarkan_pada_request_berikutnya(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($this->admin(), 'admin')
            ->post("/admin/pengguna/{$user->id}/blokir", ['reason' => 'Penipuan']);

        // Sebut guard 'web' secara eksplisit. Ini sekaligus mengembalikan guard bawaan
        // ke 'web' setelah tadi diubah oleh actingAs(..., 'admin').
        $this->actingAs($user, 'web')
            ->get('/profil')
            ->assertRedirectToRoute('login');

        $this->assertGuest('web');
    }
}