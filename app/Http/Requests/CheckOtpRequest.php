<?php

namespace App\Http\Requests;

use App\Rules\NomorSeluler;
use Illuminate\Foundation\Http\FormRequest;

// Validasi permintaan memeriksa kode OTP.
class CheckOtpRequest extends FormRequest
{
    protected $redirectRoute = 'verifikasi.show';

    public function authorize(): bool
    {
        return true;
    }

    // Berjalan SEBELUM validasi: membuang spasi di kode, mis. "123 456" (hasil salin-tempel) menjadi "123456".
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('kode'))) {
            $this->merge(['kode' => preg_replace('/\s+/', '', $this->input('kode'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nomor' => ['bail', 'required', 'string', 'max:30', new NomorSeluler()],
            // digits:6 = tepat 6 angka. Format yang salah ditolak DI SINI dan tidak menghabiskan jatah percobaan.
            'kode' => ['bail', 'required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'nomor.required' => 'Nomor WhatsApp wajib diisi.',
            'nomor.max' => 'Nomor WhatsApp terlalu panjang.',
            'kode.required' => 'Kode verifikasi wajib diisi.',
            'kode.digits' => 'Kode verifikasi harus terdiri dari 6 angka.',
        ];
    }
}