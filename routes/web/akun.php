<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RoleSelectionController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminUserSanctionController;

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
    Route::middleware('guest:admin')->group(function () {
        Route::get('/masuk', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/masuk', [AdminAuthController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('login.store');

        Route::get('/masuk/2fa', [AdminTwoFactorController::class, 'create'])->name('2fa');
        Route::post('/masuk/2fa', [AdminTwoFactorController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('2fa.store');
    });

    Route::middleware(['auth:admin', 'role:admin'])->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::post('/keluar', [AdminAuthController::class, 'destroy'])->name('logout');
  
        Route::get('/pengguna', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/{user}', [AdminUserController::class, 'show'])->name('users.show');

        Route::post('/pengguna/{user}/peringatan', [AdminUserSanctionController::class, 'warn'])->name('users.warn');
        Route::post('/pengguna/{user}/suspend', [AdminUserSanctionController::class, 'suspend'])->name('users.suspend');
        Route::post('/pengguna/{user}/blokir', [AdminUserSanctionController::class, 'ban'])->name('users.ban');
        Route::post('/pengguna/{user}/pulihkan', [AdminUserSanctionController::class, 'restore'])->name('users.restore');
    });
});