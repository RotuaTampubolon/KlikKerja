{{-- Dipakai sebagai: <x-verification-badge :status="$user->verification_status" /> --}}
@props(['status'])

@php
    // Pengaman: kalau status kosong, anggap belum terverifikasi.
    $status = $status ?? \App\Enums\VerificationStatus::Unverified;

    $classes = match ($status->value) {
        'verified' => 'bg-green-100 text-green-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<span class="inline-block rounded-full px-3 py-1 text-xs font-medium {{ $classes }}">
    {{ $status->label() }}
</span>