<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Periksa Verifikasi - KlikKerja Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 p-4">
    <main class="mx-auto max-w-5xl rounded-xl bg-white p-6 shadow">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Periksa Verifikasi</h1>
            <a href="{{ route('admin.verifikasi.index') }}" class="text-sm text-gray-500 underline hover:text-gray-700">Kembali ke antrean</a>
        </div>

        {{-- Penolakan dari service (mis. sudah diputuskan Admin lain, nomor dipakai akun lain), kunci error 'keputusan' --}}
        @error('keputusan')
            <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $message }}</div>
        @enderror

        {{-- Data pengajuan. dl/dt/dd = daftar "label: nilai" yang bermakna bagi pembaca layar. --}}
        <dl class="mt-5 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-gray-500">Nama</dt>
                <dd class="font-medium">{{ $verification->user->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Email akun</dt>
                <dd class="font-medium">{{ $verification->user->email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Peran</dt>
                <dd class="font-medium">{{ $verification->user->role?->value === 'employer' ? 'Pemberi Kerja' : 'Pencari Kerja' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Nomor WhatsApp (sudah lolos OTP)</dt>
                <dd class="font-medium">{{ $verification->phone_number }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Dikirim pada</dt>
                <dd class="font-medium">{{ $verification->submitted_at->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>

        {{-- KTP dan selfie BERDAMPINGAN (panduan). Gambar dimuat lewat endpoint Tahap 3 yang khusus Admin,
             bukan dari URL publik. Setiap pemuatan gambar tercatat di audit log. --}}
        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <figure>
                <figcaption class="mb-2 text-sm font-semibold text-gray-700">Foto KTP</figcaption>
                <a href="{{ route('verifikasi.dokumen.show', ['verification' => $verification->id, 'type' => 'ktp']) }}" target="_blank" rel="noopener">
                    <img src="{{ route('verifikasi.dokumen.show', ['verification' => $verification->id, 'type' => 'ktp']) }}"
                         alt="Foto KTP {{ $verification->user->name }}" class="w-full rounded-lg border object-contain">
                </a>
            </figure>
            <figure>
                <figcaption class="mb-2 text-sm font-semibold text-gray-700">Selfie memegang KTP</figcaption>
                <a href="{{ route('verifikasi.dokumen.show', ['verification' => $verification->id, 'type' => 'selfie']) }}" target="_blank" rel="noopener">
                    <img src="{{ route('verifikasi.dokumen.show', ['verification' => $verification->id, 'type' => 'selfie']) }}"
                         alt="Selfie memegang KTP {{ $verification->user->name }}" class="w-full rounded-lg border object-contain">
                </a>
            </figure>
        </div>
        <p class="mt-2 text-xs text-gray-500">Klik gambar untuk membuka ukuran penuh di tab baru.</p>

        @if ($menunggu)
            {{-- BELUM diputuskan: tampilkan dua form keputusan --}}
            <div class="mt-8 grid gap-6 border-t pt-6 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.verifikasi.setujui', $verification) }}"
                      onsubmit="return confirm('Setujui verifikasi ini? Keputusan tidak dapat diubah.')">
                    {{-- Token anti-CSRF: wajib di setiap form POST --}}
                    @csrf
                    <p class="mb-3 text-sm text-gray-600">Pastikan KTP terbaca jelas dan wajah pada selfie sesuai dengan foto KTP.</p>
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                        Setujui verifikasi
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.verifikasi.tolak', $verification) }}">
                    @csrf
                    <label for="alasan" class="block text-sm font-medium text-gray-700">Alasan penolakan (dibaca pengguna)</label>
                    {{-- old('alasan') mengisi ulang isian jika validasi gagal, agar Admin tidak mengetik dari awal --}}
                    <textarea id="alasan" name="alasan" rows="3" required minlength="10" maxlength="500"
                              placeholder="Contoh: Foto KTP buram, nomor NIK tidak terbaca."
                              class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100">{{ old('alasan') }}</textarea>
                    @error('alasan')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="mt-3 w-full rounded-lg bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700">
                        Tolak verifikasi
                    </button>
                </form>
            </div>
        @else
            {{-- SUDAH diputuskan: tampilkan hasilnya, tanpa tombol (status akhir tidak dapat diubah) --}}
            <div class="mt-8 border-t pt-6 text-sm">
                @if ($verification->status->value === 'verified')
                    <p class="font-semibold text-emerald-700">Pengajuan ini sudah disetujui.</p>
                @else
                    <p class="font-semibold text-red-700">Pengajuan ini sudah ditolak.</p>
                    <p class="mt-1 text-gray-700">Alasan: {{ $verification->rejected_reason }}</p>
                @endif
                <p class="mt-1 text-gray-500">
                    Oleh {{ $verification->reviewer?->name ?? 'Admin yang sudah dihapus' }}
                    pada {{ $verification->reviewed_at?->format('d/m/Y H:i') }}
                </p>
            </div>
        @endif
    </main>
</body>
</html>