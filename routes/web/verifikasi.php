<?php

use App\Http\Controllers\Verification\OtpController;
use App\Http\Controllers\Verification\VerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Verification\DocumentUploadController;

// Pengguna biasa (guard web) yang sudah login, sudah memilih peran, dan akunnya tidak diblokir.
// Sengaja TANPA 'identity.verified': halaman ini justru tempat pengguna menjadi terverifikasi.
Route::middleware(['auth', 'role.chosen', 'not.blocked'])
    ->prefix('verifikasi')
    ->name('verifikasi.')
    ->group(function () {
        // GET /verifikasi : menampilkan halaman verifikasi.
        Route::get('/', [VerificationController::class, 'show'])->name('show');

        // throttle:10,1 = maksimal 10 permintaan per menit per pengguna. Lapisan pertama sebelum
        // aturan jeda/kuota/percobaan di OtpService.
        Route::post('/otp/kirim', [OtpController::class, 'kirim'])
            ->middleware('throttle:10,1')
            ->name('otp.kirim');

        Route::post('/otp/cek', [OtpController::class, 'cek'])
            ->middleware('throttle:10,1')
            ->name('otp.cek');

        // POST /verifikasi/dokumen : unggah KTP dan selfie. Dibatasi 5 kali per menit.
        Route::post('/dokumen', [DocumentUploadController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('dokumen.store');
    });
