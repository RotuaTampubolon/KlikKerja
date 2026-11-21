<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Test unit murni: tidak memakai database, jadi berjalan sangat cepat.
class PhoneNumberTest extends TestCase
{
    // Satu test dijalankan berulang kali, sekali untuk tiap baris data dari nomorValid().
    #[DataProvider('nomorValid')]
    public function test_nomor_valid_dinormalisasi_ke_format_62(string $input, string $diharapkan): void
    {
        $this->assertSame($diharapkan, PhoneNumber::normalize($input));
    }

    // Sama, tetapi untuk nomor yang HARUS ditolak (hasilnya null).
    #[DataProvider('nomorTidakValid')]
    public function test_nomor_tidak_valid_ditolak(?string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
    }

    // Daftar data: 'nama kasus' => [input, hasil yang diharapkan].
    public static function nomorValid(): array
    {
        return [
            'format lokal 08'            => ['081234567890', '6281234567890'],
            'lokal dengan strip'         => ['0812-3456-7890', '6281234567890'],
            'lokal dengan spasi'         => ['0812 3456 7890', '6281234567890'],
            'lokal dengan kurung'        => ['(0812) 3456 7890', '6281234567890'],
            'internasional plus'         => ['+6281234567890', '6281234567890'],
            'internasional plus spasi'   => ['+62 812-3456-7890', '6281234567890'],
            'sudah format 62'            => ['6281234567890', '6281234567890'],
            'lupa angka nol'             => ['81234567890', '6281234567890'],
            'awalan 00'                  => ['006281234567890', '6281234567890'],
            'spasi di pinggir'           => ['  081234567890  ', '6281234567890'],
        ];
    }

    public static function nomorTidakValid(): array
    {
        return [
            'kosong'                 => [''],
            'hanya spasi'            => ['   '],
            'null'                   => [null],
            'huruf'                  => ['abc'],
            'huruf di tengah'        => ['0812abc3456'],
            'telepon rumah'          => ['021555123'],
            'terlalu pendek'         => ['0812'],
            'terlalu panjang'        => ['08123456789012345'],
            'nomor asing'            => ['+14155552671'],
            'plus lalu nol'          => ['+0812345678'],
            'plus nyasar di tengah'  => ['0812+3456789'],
            '62 diikuti nol'         => ['620812345678'],
        ];
    }
}