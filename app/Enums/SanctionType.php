<?php

namespace App\Enums;

// Jenis tindakan Admin terhadap akun. 
// peringatan, suspend, blokir, pulihkan.
enum SanctionType: string
{
    case Warning = 'warning';         // peringatan (tidak membatasi akses)
    case Suspension = 'suspension';   // penangguhan sementara (pakai expires_at)
    case Ban = 'ban';                 // blokir
    case Restoration = 'restoration'; // pemulihan akun
}