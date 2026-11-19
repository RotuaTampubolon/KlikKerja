<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SanctionReasonRequest extends FormRequest
{
    // Akses dijaga middleware route (auth:admin, role:admin).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Alasan wajib untuk setiap tindakan: jadi catatan riwayat yang bisa dipertanggungjawabkan.
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}