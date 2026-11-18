<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Halaman masuk. Nama 'login' WAJIB: middleware auth bawaan Laravel
// mengarahkan tamu ke route bernama 'login'.
Route::view('/masuk', 'auth.masuk')->name('login');

// Alur Google OAuth.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');