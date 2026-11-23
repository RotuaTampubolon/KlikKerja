<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validasi unggahan KTP dan selfie memegang KTP.
class UploadDocumentsRequest extends FormRequest
{
    protected $redirectRoute = 'verifikasi.show';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Aturan yang sama untuk kedua foto (panduan: JPG/JPEG/PNG, maksimal 4 MB per file).
        // mimes memeriksa ISI file (bukan hanya ekstensi namanya). max:4096 = 4096 KB = 4 MB.
        $aturanFoto = ['bail', 'required', 'file', 'mimes:jpg,jpeg,png', 'max:4096'];

        return [
            'ktp' => $aturanFoto,
            'selfie' => $aturanFoto,
        ];
    }

    public function messages(): array
    {
        return [
            'ktp.required' => 'Foto KTP wajib diunggah.',
            'ktp.file' => 'Foto KTP harus berupa file.',
            'ktp.mimes' => 'Foto KTP harus berformat JPG, JPEG, atau PNG.',
            'ktp.max' => 'Ukuran foto KTP maksimal 4 MB.',
            'ktp.uploaded' => 'Foto KTP gagal diunggah. Pastikan ukurannya tidak lebih dari 4 MB.',

            'selfie.required' => 'Foto selfie memegang KTP wajib diunggah.',
            'selfie.file' => 'Foto selfie harus berupa file.',
            'selfie.mimes' => 'Foto selfie harus berformat JPG, JPEG, atau PNG.',
            'selfie.max' => 'Ukuran foto selfie maksimal 4 MB.',
            'selfie.uploaded' => 'Foto selfie gagal diunggah. Pastikan ukurannya tidak lebih dari 4 MB.',
        ];
    }
}