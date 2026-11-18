<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RoleSelectionController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Admin\AdminAuthController;

// Halaman masuk. Nama 'login' WAJIB: middleware auth bawaan Laravel
// mengarahkan tamu ke route bernama 'login'.
Route::view('/masuk', 'auth.masuk')->name('login');

// Alur Google OAuth.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

// Hanya pengguna yang sudah login (middleware 'auth') yang boleh memilih peran.
// Tamu otomatis diarahkan ke route bernama 'login' (/masuk).
Route::middleware('auth')->group(function () {
    Route::get('/daftar/peran', [RoleSelectionController::class, 'show'])->name('role.show');
    Route::post('/daftar/peran', [RoleSelectionController::class, 'store'])->name('role.store');
    Route::post('/keluar', LogoutController::class)->name('logout');
});

// Area Admin: URL berawalan /admin, nama route berawalan admin.
Route::prefix('admin')->name('admin.')->group(function () {
    // Halaman khusus tamu. throttle:5,1 = maksimal 5 percobaan login per menit per IP.
    Route::middleware('guest:admin')->group(function () {
        Route::get('/masuk', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/masuk', [AdminAuthController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('login.store');
    });

    // Area yang hanya boleh dimasuki Admin. auth:admin mengirim tamu ke /admin/masuk,
    // role:admin memastikan perannya (konsisten dengan PRD).
    Route::middleware(['auth:admin', 'role:admin'])->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::post('/keluar', [AdminAuthController::class, 'destroy'])->name('logout');
    });
});