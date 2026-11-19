<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // Pengguna Pencari Kerja beserta profilnya.
    private function seeker(): User
    {
        $user = User::factory()->seeker()->create();
        $user->seekerProfile()->create();

        return $user;
    }

    public function test_menambah_pengalaman(): void
    {
        $user = $this->seeker();

        $this->actingAs($user)
            ->post('/profil/pengalaman', ['title' => 'Pelayan warung', 'workplace' => 'Warung Bu Sari'])
            ->assertRedirectToRoute('profile.show');

        $this->assertDatabaseHas('work_experiences', [
            'seeker_profile_id' => $user->seekerProfile->id,
            'title' => 'Pelayan warung',
        ]);
    }

    public function test_judul_wajib_diisi(): void
    {
        $this->actingAs($this->seeker())
            ->post('/profil/pengalaman', ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_tanggal_selesai_tidak_boleh_sebelum_mulai(): void
    {
        $this->actingAs($this->seeker())
            ->post('/profil/pengalaman', [
                'title' => 'Kerja',
                'started_at' => '2024-05-01',
                'ended_at' => '2024-01-01',
            ])
            ->assertSessionHasErrors('ended_at');
    }

    public function test_maksimal_10_pengalaman(): void
    {
        $user = $this->seeker();
        $user->seekerProfile->workExperiences()->createMany(
            array_fill(0, WorkExperience::MAX_PER_PROFILE, ['title' => 'Kerja'])
        );

        $this->actingAs($user)
            ->post('/profil/pengalaman', ['title' => 'Kerja ke-11'])
            ->assertSessionHasErrors('experience');

        $this->assertSame(10, $user->seekerProfile->workExperiences()->count());
    }

    public function test_mengubah_pengalaman_sendiri(): void
    {
        $user = $this->seeker();
        $experience = $user->seekerProfile->workExperiences()->create(['title' => 'Lama']);

        $this->actingAs($user)
            ->put("/profil/pengalaman/{$experience->id}", ['title' => 'Baru'])
            ->assertRedirectToRoute('profile.show');

        $this->assertDatabaseHas('work_experiences', ['id' => $experience->id, 'title' => 'Baru']);
    }

    public function test_tidak_bisa_mengubah_pengalaman_orang_lain(): void
    {
        $pemilik = $this->seeker();
        $experience = $pemilik->seekerProfile->workExperiences()->create(['title' => 'Milik orang lain']);

        $this->actingAs($this->seeker())
            ->put("/profil/pengalaman/{$experience->id}", ['title' => 'Dibajak'])
            ->assertForbidden();

        $this->assertDatabaseHas('work_experiences', ['id' => $experience->id, 'title' => 'Milik orang lain']);
    }

    public function test_menghapus_pengalaman_sendiri(): void
    {
        $user = $this->seeker();
        $experience = $user->seekerProfile->workExperiences()->create(['title' => 'Hapus aku']);

        $this->actingAs($user)->delete("/profil/pengalaman/{$experience->id}")
            ->assertRedirectToRoute('profile.show');

        $this->assertDatabaseMissing('work_experiences', ['id' => $experience->id]);
    }

    public function test_tidak_bisa_menghapus_pengalaman_orang_lain(): void
    {
        $pemilik = $this->seeker();
        $experience = $pemilik->seekerProfile->workExperiences()->create(['title' => 'Tetap ada']);

        $this->actingAs($this->seeker())
            ->delete("/profil/pengalaman/{$experience->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('work_experiences', ['id' => $experience->id]);
    }

    public function test_daftar_pengalaman_tampil_di_profil(): void
    {
        $user = $this->seeker();
        $user->seekerProfile->workExperiences()->create(['title' => 'Tukang kebun']);

        $this->actingAs($user)->get('/profil')->assertOk()->assertSee('Tukang kebun');
    }
}