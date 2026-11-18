<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil - KlikKerja</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 p-4">
    <main class="mx-auto w-full max-w-2xl space-y-6">

        {{-- Identitas: nama dan foto berasal dari tabel users --}}
        <section class="rounded-xl bg-white p-6 shadow">
            <div class="flex items-center gap-4">
                @if ($user->avatar_url)
                    {{-- no-referrer: foto Google kadang gagal dimuat tanpa ini --}}
                    <img src="{{ $user->avatar_url }}" alt="Foto {{ $user->name }}"
                         class="h-16 w-16 rounded-full object-cover" referrerpolicy="no-referrer">
                @endif
                <div>
                    <h1 class="text-xl font-bold">{{ $user->name }}</h1>
                    <p class="text-sm text-gray-600">Pencari Kerja</p>
                    <div class="mt-1">
                        <x-verification-badge :status="$user->verification_status" />
                    </div>
                </div>
            </div>
        </section>

        {{-- Ringkasan --}}
        <section class="rounded-xl bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Ringkasan</h2>

            @if (session('status'))
                <p class="mt-2 text-sm text-green-700">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-3">
                @csrf
                {{-- Browser hanya mengenal GET dan POST; @method memberi tahu Laravel ini PUT --}}
                @method('PUT')

                <textarea name="summary" rows="5" maxlength="300"
                          class="w-full rounded-lg border p-3 text-sm"
                          placeholder="Ceritakan singkat tentang diri dan keahlian Anda (maks. 300 karakter)">{{ old('summary', $profile->summary) }}</textarea>

                @error('summary')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Simpan
                </button>
            </form>
        </section>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">Keluar</button>
        </form>
    </main>
</body>
</html>