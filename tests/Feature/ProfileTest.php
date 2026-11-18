<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // View memakai @vite, yang butuh hasil build asset. Di test kita matikan
        // supaya test tidak bergantung pada `npm run build` (juga di komputer teman).
        $this->withoutVite();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/profil')->assertRedirectToRoute('login');
    }

    public function test_profil_dibuat_otomatis_saat_dibuka(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)->get('/profil')->assertOk();

        $this->assertDatabaseHas('seeker_profiles', ['user_id' => $user->id]);
    }

    public function test_ringkasan_tersimpan(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)
            ->put('/profil', ['summary' => 'Berpengalaman di warung'])
            ->assertRedirectToRoute('profile.show');

        $this->assertDatabaseHas('seeker_profiles', [
            'user_id' => $user->id,
            'summary' => 'Berpengalaman di warung',
        ]);
    }

    public function test_ringkasan_lebih_dari_300_karakter_ditolak(): void
    {
        $user = User::factory()->seeker()->create();

        $this->actingAs($user)
            ->put('/profil', ['summary' => str_repeat('a', 301)])
            ->assertSessionHasErrors('summary');
    }

    public function test_badge_menampilkan_status_verifikasi(): void
    {
        $belum = User::factory()->seeker()->create();
        $sudah = User::factory()->seeker()->identityVerified()->create();

        $this->actingAs($belum)->get('/profil')->assertSee('Belum terverifikasi');
        $this->actingAs($sudah)->get('/profil')->assertSee('Terverifikasi');
    }

    public function test_pemberi_kerja_tidak_bisa_membuka_profil_pencari(): void
    {
        $user = User::factory()->employer()->create();

        $this->actingAs($user)->get('/profil')->assertForbidden();
    }
}