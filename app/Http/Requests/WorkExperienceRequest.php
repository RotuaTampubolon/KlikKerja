<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkExperienceRequest extends FormRequest
{
    // Hak akses dijaga middleware di route (auth, role:seeker, can:...).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'workplace' => ['nullable', 'string', 'max:100'],
            'started_at' => ['nullable', 'date', 'before_or_equal:today', 'required_with:ended_at'],
            // Kosong = masih bekerja di sana. Kalau diisi, tidak boleh sebelum tanggal mulai.
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Jabatan atau jenis pekerjaan wajib diisi.',
            'started_at.before_or_equal' => 'Tanggal mulai tidak boleh di masa depan.',
            'started_at.required_with' => 'Isi tanggal mulai jika tanggal selesai diisi.',
            'ended_at.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }
}