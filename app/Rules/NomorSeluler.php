<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Aturan validasi buatan sendiri: nomor harus berupa nomor seluler Indonesia yang bisa dinormalisasi.
// Dipakai di dua Form Request (kirim dan cek), jadi cukup ditulis sekali di sini.
class NomorSeluler implements ValidationRule
{
    // Laravel memanggil method ini; $fail("pesan") menandai input sebagai tidak valid.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::normalize($value) === null) {
            $fail('Nomor WhatsApp tidak valid. Gunakan nomor seluler Indonesia, misalnya 0812xxxxxxxx.');
        }
    }
}