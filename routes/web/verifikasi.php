<?php

use App\Http\Controllers\Verification\OtpController;
use App\Http\Controllers\Verification\VerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Verification\DocumentUploadController;
use App\Http\Controllers\Verification\DocumentViewController;


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

// Menampilkan dokumen identitas: HANYA Admin (guard admin).
//
// Kenapa di luar grup di atas? Grup itu memakai guard 'web' (pengguna biasa). Route ini butuh
// guard 'admin', jadi middleware-nya berbeda.
//
// auth:admin  = harus sudah login sebagai Admin. Kalau belum, dialihkan ke halaman masuk.
// role:admin  = middleware milik Rotua yang memastikan yang masuk benar-benar berperan Admin.
//
// Catatan: karena URL ini tidak berawalan /admin, tamu dan pengguna biasa dialihkan ke /masuk
// (aturan redirect di bootstrap/app.php milik Rotua hanya mengarahkan ke login Admin untuk
// URL berawalan /admin). Ini TIDAK membuka akses: mereka tetap tidak bisa melihat file.
Route::middleware(['auth:admin', 'role:admin'])
    ->get('/verifikasi/dokumen/{verification}/{type}', [DocumentViewController::class, 'show'])
    // whereIn = {type} hanya boleh bernilai 'ktp' atau 'selfie'. Nilai lain (mis. /paspor)
    // tidak cocok dengan route ini sama sekali, jadi Laravel langsung membalas 404.
    ->whereIn('type', ['ktp', 'selfie'])
    // Nama route, dipakai di kode dan test: route('verifikasi.dokumen.show', [...]).
    ->name('verifikasi.dokumen.show');