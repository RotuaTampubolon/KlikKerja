<?php

namespace App\Http\Requests;

use App\Rules\NomorSeluler;
use Illuminate\Foundation\Http\FormRequest;

// Validasi permintaan kirim OTP.
class SendOtpRequest extends FormRequest
{
    // Kalau validasi gagal, kembali ke halaman verifikasi (bukan "halaman sebelumnya" yang bisa tidak pasti).
    protected $redirectRoute = 'verifikasi.show';

    // Akses sudah dijaga middleware auth, role.chosen, dan not.blocked di route.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'bail' = berhenti di aturan pertama yang gagal, supaya NomorSeluler tidak menerima data aneh.
            'nomor' => ['bail', 'required', 'string', 'max:30', new NomorSeluler()],
        ];
    }

    public function messages(): array
    {
        return [
            'nomor.required' => 'Nomor WhatsApp wajib diisi.',
            'nomor.max' => 'Nomor WhatsApp terlalu panjang.',
        ];
    }
}