<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChooseRoleRequest extends FormRequest
{
    // Akses ke route sudah dijaga middleware 'auth'. Pengecekan
    // "peran sudah dipilih" dilakukan di controller (lebih ramah: redirect, bukan 403).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Rule::enum hanya menerima nilai yang ada di enum Role (seeker/employer).
            'role' => ['required', Rule::enum(Role::class)],
            // 'accepted' = checkbox harus dicentang.
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Pilih salah satu peran.',
            'role.enum' => 'Peran yang dipilih tidak valid.',
            'consent.accepted' => 'Anda harus menyetujui Syarat dan Ketentuan serta Kebijakan Privasi.',
        ];
    }
}