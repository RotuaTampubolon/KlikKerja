<?php

namespace App\Enums;

// Jenis pekerjaan di antrean Admin. Satu enum = satu daftar pilihan tetap.
enum AdminTaskType: string
{
    case Verification = 'verification'; // pengajuan verifikasi identitas (Anggota 2)
    case Report = 'report';             // laporan profil/lowongan (Anggota 3)
    case Ticket = 'ticket';             // tiket bantuan (Anggota 4)
}