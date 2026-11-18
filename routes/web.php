<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route modul Akun (Anggota 1). Setiap anggota punya file sendiri di routes/web/.
require __DIR__.'/web/akun.php';
