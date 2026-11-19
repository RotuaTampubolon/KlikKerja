<?php

namespace App\Enums;

// Enum = tipe data yang nilainya dibatasi pada pilihan tetap.
// "string" di sini artinya enum ini "backed": tiap pilihan punya nilai teks
// yang disimpan di database (kolom users.role).
enum Role: string
{
    // Nama case dipakai di kode PHP: Role::Seeker
    // Nilai di kanan dipakai di database: 'seeker'
    case Seeker = 'seeker';     // Pencari Kerja
    case Employer = 'employer'; // Pemberi Kerja

    // Admin sengaja TIDAK ada di sini: Admin punya tabel `admins` sendiri,
    // bukan peran di tabel `users`.

    public function label(): string
    {
        return match ($this) {
            self::Seeker => 'Pencari Kerja',
            self::Employer => 'Pemberi Kerja',
        };
    }
}