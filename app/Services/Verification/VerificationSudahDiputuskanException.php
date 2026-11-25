<?php

namespace App\Services\Verification;

use App\Enums\VerificationStatus;
use RuntimeException;

// Dilempar saat Admin mencoba memutuskan pengajuan yang sudah punya keputusan.
// Membawa status saat ini supaya controller bisa menampilkan pesan yang tepat.
class VerificationSudahDiputuskanException extends RuntimeException
{
    public function __construct(public readonly VerificationStatus $statusSaatIni)
    {
        $label = match ($statusSaatIni) {
            VerificationStatus::Verified => 'disetujui',
            VerificationStatus::Rejected => 'ditolak',
            default => 'sudah diproses',
        };

        parent::__construct("Pengajuan ini sudah {$label} sebelumnya.");
    }
}