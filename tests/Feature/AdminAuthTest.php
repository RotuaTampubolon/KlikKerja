<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
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

    public function test_tamu_diarahkan_ke_login_admin(): void
    {
        $this->get('/admin')->assertRedirectToRoute('admin.login');
    }

    public function test_login_admin_berhasil(): void
    {
        $this->admin();

        $this->post('/admin/masuk', ['email' => 'admin@example.test', 'password' => 'rahasia123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticated('admin');
        $this->assertGuest('web'); // login Admin tidak menjadikanmu pengguna biasa
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

        // Login di guard 'web' tidak berlaku di guard 'admin'.
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