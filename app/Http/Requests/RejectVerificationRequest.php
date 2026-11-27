<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validasi penolakan verifikasi: alasan WAJIB (panduan: "penolakan wajib menyertakan alasan").
class RejectVerificationRequest extends FormRequest
{
    // Akses sudah dijaga middleware auth:admin + role:admin di route.
    public function authorize(): bool
    {
        return true;
    }

    // Jika validasi gagal, kembali ke halaman detail pengajuan yang SAMA. Karena alamatnya memuat id
    // pengajuan, kita tidak bisa memakai $redirectRoute biasa. $this->route('verification') adalah
    // objek Verification hasil route model binding.
    protected function getRedirectUrl(): string
    {
        return route('admin.verifikasi.show', $this->route('verification'));
    }

    public function rules(): array
    {
        // min:10 mencegah alasan asal-asalan seperti "x" atau "tidak". Alasan ini dibaca pengguna
        // dan menentukan apa yang harus ia perbaiki. max:500 membatasi ukuran data.
        return [
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penolakan wajib diisi.',
            'alasan.min' => 'Alasan penolakan minimal 10 karakter agar jelas bagi pengguna.',
            'alasan.max' => 'Alasan penolakan maksimal 500 karakter.',
        ];
    }
}