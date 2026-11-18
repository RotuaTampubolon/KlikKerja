<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RoleSelectionController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ProfileController;

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

// Halaman profil: urutan middleware 
// (login -> tidak diblokir -> sudah pilih peran -> peran sesuai).
Route::middleware(['auth', 'not.blocked', 'role.chosen', 'role:seeker'])->group(function () {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
});
