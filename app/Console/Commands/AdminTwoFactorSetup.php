<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

class AdminTwoFactorSetup extends Command
{
    protected $signature = 'admin:2fa {email : Email Admin}';

    protected $description = 'Membuat secret TOTP baru untuk Admin dan menampilkan data untuk aplikasi authenticator';

    public function handle(Google2FA $google2fa): int
    {
        $admin = Admin::where('email', $this->argument('email'))->first();

        if ($admin === null) {
            $this->error('Admin dengan email itu tidak ditemukan.');

            return self::FAILURE;
        }

        $secret = $google2fa->generateSecretKey();

        // two_factor_secret tidak fillable, jadi diisi lewat forceFill. Terenkripsi otomatis (cast).
        $admin->forceFill(['two_factor_secret' => $secret])->save();

        $this->info('2FA diaktifkan. Tambahkan ke aplikasi authenticator (Google Authenticator, dll.):');
        $this->line("  Setup key : {$secret}");
        $this->line('  URL       : '.$google2fa->getQRCodeUrl(config('app.name'), $admin->email, $secret));
        $this->warn('Menjalankan ulang perintah ini membuat secret baru dan mematikan yang lama.');

        return self::SUCCESS;
    }
}