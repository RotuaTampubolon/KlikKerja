<?php

namespace Tests\Support;

use App\Models\User;
use App\Models\Verification;
use App\Services\Verification\VerificationNotifier;

// Pemberi tahu palsu untuk test: menyimpan semua pesan di memori supaya bisa diperiksa.
class FakeVerificationNotifier implements VerificationNotifier
{
    // Daftar kiriman: [['user_id' => 1, 'verification_id' => 2, 'pesan' => '...'], ...]
    public array $terkirim = [];

    public function kirimHasil(User $user, Verification $verification, string $pesan): void
    {
        $this->terkirim[] = ['user_id' => $user->id, 'verification_id' => $verification->id, 'pesan' => $pesan];
    }
}