<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // HANYA untuk lingkungan lokal. Di production, akun Admin dibuat manual
        // (misalnya lewat tinker), bukan dari password yang tertulis di kode.
        if (! app()->environment('local')) {
            return;
        }

        // updateOrCreate: aman dijalankan berulang (tidak membuat akun ganda).
        Admin::updateOrCreate(
            ['email' => 'admin@klikkerja.test'],
            ['name' => 'Admin KlikKerja', 'password' => 'password'], // di-hash otomatis oleh cast
        );
    }
}