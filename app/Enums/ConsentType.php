<?php

namespace App\Enums;

// Dokumen yang disetujui pengguna saat mendaftar (syarat dan kebijakan privasi).
enum ConsentType: string
{
    case Terms = 'terms';     // Syarat dan Ketentuan
    case Privacy = 'privacy'; // Kebijakan Privasi
}