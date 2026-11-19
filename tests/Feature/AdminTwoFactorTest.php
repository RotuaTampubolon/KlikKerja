<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->google2fa = new Google2FA;
    }

    private function admin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Admin Uji',
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ]);
        $admin->forceFill(['two_factor_secret' => $this->google2fa->generateSecretKey()])->save();

        return $admin;
    }

    // Sesi "sudah lolos password, menunggu kode".
    private function menungguKode(Admin $admin, ?int $expiresAt = null, int $attempts = 0): array
    {
        return ['admin_2fa' => [
            'id' => $admin->id,
            'expires_at' => $expiresAt ?? now()->addMinutes(5)->timestamp,
            'attempts' => $attempts,
        ]];
    }

    public function test_halaman_2fa_tanpa_sesi_diarahkan_ke_login(): void
    {
        $this->get('/admin/masuk/2fa')->assertRedirectToRoute('admin.login');
    }

    public function test_sesi_menunggu_yang_kedaluwarsa_ditolak(): void
    {
        $admin = $this->admin();

        $this->withSession($this->menungguKode($admin, now()->subMinute()->timestamp))
            ->get('/admin/masuk/2fa')
            ->assertRedirectToRoute('admin.login');
    }

    public function test_kode_benar_membuat_admin_login(): void
    {
        $admin = $this->admin();
        $kode = $this->google2fa->getCurrentOtp($admin->two_factor_secret);

        $this->withSession($this->menungguKode($admin))
            ->post('/admin/masuk/2fa', ['code' => $kode])
            ->assertRedirect('/admin')
            ->assertSessionMissing('admin_2fa');

        $this->assertAuthenticated('admin');
    }

    public function test_kode_salah_ditolak(): void
    {
        $admin = $this->admin();

        $this->withSession($this->menungguKode($admin))
            ->post('/admin/masuk/2fa', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest('admin');
    }

    public function test_kode_harus_6_digit(): void
    {
        $admin = $this->admin();

        $this->withSession($this->menungguKode($admin))
            ->post('/admin/masuk/2fa', ['code' => '123'])
            ->assertSessionHasErrors('code');
    }

    public function test_kode_salah_ke_5_memutus_sesi_menunggu(): void
    {
        $admin = $this->admin();

        // Sudah 4 kali salah; percobaan salah ke-5 memutus sesi.
        $this->withSession($this->menungguKode($admin, attempts: 4))
            ->post('/admin/masuk/2fa', ['code' => '000000'])
            ->assertRedirectToRoute('admin.login')
            ->assertSessionMissing('admin_2fa');

        $this->assertGuest('admin');
    }
}