<?php

namespace Tests\Feature;

use App\Enums\SanctionType;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    // Database dikosongkan dan dimigrasi ulang di setiap test (SQLite in-memory),
    // jadi data satu test tidak bocor ke test lain dan MySQL-mu tidak tersentuh.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route sementara yang hanya ada selama test berjalan.
        Route::middleware(['web', 'auth', 'not.blocked', 'role.chosen'])->get('/uji/umum', fn () => 'ok');
        Route::middleware(['web', 'auth', 'role:seeker'])->get('/uji/seeker', fn () => 'ok');
        Route::middleware(['web', 'auth', 'role:employer'])->get('/uji/employer', fn () => 'ok');
        Route::middleware(['web', 'auth', 'identity.verified'])->get('/uji/terverifikasi', fn () => 'ok');
        Route::middleware(['web', 'role:admin'])->get('/uji/admin', fn () => 'ok');
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/uji/umum')->assertRedirectToRoute('login');
    }

    public function test_pengguna_tanpa_peran_diarahkan_memilih_peran(): void
    {
        $user = User::factory()->create(); // role = null

        $this->actingAs($user)->get('/uji/umum')->assertRedirect('/daftar/peran');
    }

    public function test_peran_yang_sesuai_lolos(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/uji/seeker')->assertOk();
    }

    public function test_salah_peran_mendapat_403(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/uji/employer')->assertForbidden();
    }

    public function test_belum_terverifikasi_diarahkan_ke_verifikasi(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/uji/terverifikasi')->assertRedirect('/verifikasi');
    }

    public function test_sudah_terverifikasi_lolos(): void
    {
        $user = User::factory()->seeker()->identityVerified()->create();

        $this->actingAs($user)->get('/uji/terverifikasi')->assertOk();
    }

    public function test_akun_diblokir_dikeluarkan(): void
    {
        $user = User::factory()->seeker()->create();
        $user->sanctions()->create(['type' => SanctionType::Ban]);

        $this->actingAs($user)->get('/uji/umum')->assertRedirectToRoute('login');
        $this->assertGuest(); // sesi login benar-benar dihapus
    }

    public function test_suspend_yang_sudah_kedaluwarsa_lolos(): void
    {
        $user = User::factory()->seeker()->create();
        $user->sanctions()->create([
            'type' => SanctionType::Suspension,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)->get('/uji/umum')->assertOk();
    }

    public function test_admin_lolos_role_admin(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ]);

        // Argumen kedua: guard mana yang dipakai untuk "login".
        $this->actingAs($admin, 'admin')->get('/uji/admin')->assertOk();
    }

    public function test_pengguna_biasa_tidak_lolos_role_admin(): void
    {
        $user = User::factory()->seeker()->create();

        // Login di guard 'web' tidak berlaku di guard 'admin'.
        $this->actingAs($user)->get('/uji/admin')->assertForbidden();
    }
}