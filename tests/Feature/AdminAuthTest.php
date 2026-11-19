<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(bool $dengan2fa = true): Admin
    {
        $admin = Admin::create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ]);

        if ($dengan2fa) {
            $admin->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey()])->save();
        }

        return $admin;
    }

    public function test_tamu_diarahkan_ke_login_admin(): void
    {
        $this->get('/admin')->assertRedirectToRoute('admin.login');
    }

    public function test_password_benar_diarahkan_ke_kode_2fa_tanpa_login(): void
    {
        $admin = $this->admin();

        $this->post('/admin/masuk', ['email' => 'admin@example.test', 'password' => 'rahasia123'])
            ->assertRedirectToRoute('admin.2fa')
            ->assertSessionHas('admin_2fa.id', $admin->id);

        $this->assertGuest('admin'); // belum login: masih menunggu kode
    }

    public function test_admin_tanpa_2fa_ditolak(): void
    {
        $this->admin(dengan2fa: false);

        $this->post('/admin/masuk', ['email' => 'admin@example.test', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('admin_2fa');

        $this->assertGuest('admin');
    }
    public function test_password_salah_ditolak(): void
    {
        $this->admin();

        $this->post('/admin/masuk', ['email' => 'admin@example.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_email_tidak_dikenal_mendapat_pesan_yang_sama(): void
    {
        $this->admin();

        $this->post('/admin/masuk', ['email' => 'tidakada@example.test', 'password' => 'rahasia123'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
    }

    public function test_pengguna_biasa_tidak_bisa_membuka_area_admin(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/admin')->assertRedirectToRoute('admin.login');
    }

    public function test_admin_yang_login_bisa_membuka_dashboard(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk()->assertSee('Dashboard Admin');
    }

    public function test_logout_admin(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/keluar')
            ->assertRedirectToRoute('admin.login');

        $this->assertGuest('admin');
    }
}