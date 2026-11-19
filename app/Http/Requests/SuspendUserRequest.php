<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuspendUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            // Lama penangguhan dalam hari.
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
            'days.required' => 'Lama penangguhan wajib diisi.',
            'days.integer' => 'Lama penangguhan harus berupa angka.',
            'days.min' => 'Lama penangguhan minimal 1 hari.',
            'days.max' => 'Lama penangguhan maksimal 365 hari.',
        ];
    }
}