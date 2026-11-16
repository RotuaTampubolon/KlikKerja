<?php

namespace App\Enums;

// Jenis akun Pemberi Kerja. Enum membatasi nilainya pada pilihan yang sah.
enum AccountType: string
{
    case Individual = 'individual'; // perorangan
    case Business = 'business';     // usaha (warung, toko, dll.)
}