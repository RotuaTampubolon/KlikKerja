<?php

namespace App\Enums;

// Status verifikasi identitas pengguna. Diisi oleh modul Verifikasi (Anggota 2).
// Middleware identity.verified hanya mengizinkan status Verified.
enum VerificationStatus: string
{
    case Unverified = 'unverified'; // belum mengajukan
    case Pending = 'pending';       // sudah mengajukan, menunggu Admin
    case Verified = 'verified';     // disetujui Admin
    case Rejected = 'rejected';     // ditolak Admin (bisa mengajukan ulang)
}