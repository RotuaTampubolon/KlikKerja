<?php

namespace App\Support;

// Pembantu murni untuk nomor telepon Indonesia. Tidak butuh database maupun framework,
// jadi bisa diuji dengan cepat dan dipakai di mana saja.
class PhoneNumber
{
    // Mengubah berbagai cara menulis nomor menjadi "628xxxxxxxxx".
    // Mengembalikan null jika nomor tidak valid.
    public static function normalize(?string $input): ?string
    {
        // Tidak ada input sama sekali = tidak valid.
        if ($input === null) {
            return null;
        }

        // Buang spasi, strip, titik, dan kurung yang biasa dipakai orang saat menulis nomor.
        $bersih = preg_replace('/[\s\-\.\(\)]/', '', trim($input));

        // Sisanya hanya boleh angka, dengan tanda + opsional di paling depan.
        // Ini sekaligus menolak huruf dan tanda + yang nyasar di tengah nomor.
        if ($bersih === '' || ! preg_match('/^\+?\d+$/', $bersih)) {
            return null;
        }

        // Catat apakah nomor ditulis dengan awalan internasional "+".
        $adaPlus = str_starts_with($bersih, '+');

        // Ambil angkanya saja (buang tanda +).
        $digit = ltrim($bersih, '+');

        // Awalan "+" harus diikuti kode negara Indonesia (62). "+1..." atau "+0..." ditolak.
        if ($adaPlus && ! str_starts_with($digit, '62')) {
            return null;
        }

        // Awalan internasional "00": 0062812... -> 62812...
        if (str_starts_with($digit, '00')) {
            $digit = substr($digit, 2);
        }

        if (str_starts_with($digit, '0')) {
            // Format lokal: 0812... -> 62812... (angka 0 diganti 62)
            $digit = '62'.substr($digit, 1);
        } elseif (str_starts_with($digit, '8')) {
            // Pengguna lupa menulis 0: 812... -> 62812...
            $digit = '62'.$digit;
        }

        // Nomor seluler Indonesia: 628 + 8 sampai 11 angka (total 11-14 digit).
        // Ini menolak telepon rumah (021...), nomor asing, dan nomor yang terlalu pendek/panjang.
        return preg_match('/^628\d{8,11}$/', $digit) ? $digit : null;
    }
}