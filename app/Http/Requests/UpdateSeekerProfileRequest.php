<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeekerProfileRequest extends FormRequest
{
    // Akses sudah dijaga middleware (auth + role:seeker) di route.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ringkasan maksimal 300 karakter. Boleh kosong.
            'summary' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'summary.max' => 'Ringkasan maksimal 300 karakter.',
        ];
    }
}