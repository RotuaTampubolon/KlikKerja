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

                {{-- Pengalaman kerja --}}
        <section class="rounded-xl bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Pengalaman Kerja</h2>
            <p class="text-sm text-gray-500">
                {{ $experiences->count() }} dari {{ \App\Models\WorkExperience::MAX_PER_PROFILE }} entri
            </p>

            {{-- Semua pesan error kecuali milik ringkasan (yang tampil di bagiannya sendiri) --}}
            @php $experienceErrors = collect($errors->getMessages())->except('summary')->flatten(); @endphp
            @if ($experienceErrors->isNotEmpty())
                <ul class="mt-3 list-disc pl-5 text-sm text-red-600">
                    @foreach ($experienceErrors as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif

            <ul class="mt-4 space-y-3">
                @forelse ($experiences as $experience)
                    <li class="rounded-lg border p-4">
                        <p class="font-medium">{{ $experience->title }}</p>
                        @if ($experience->workplace)
                            <p class="text-sm text-gray-600">{{ $experience->workplace }}</p>
                        @endif
                        <p class="text-xs text-gray-500">
                            {{ $experience->started_at?->translatedFormat('M Y') ?? '?' }}
                            -
                            {{ $experience->ended_at?->translatedFormat('M Y') ?? 'sekarang' }}
                        </p>
                        @if ($experience->description)
                            <p class="mt-1 text-sm">{{ $experience->description }}</p>
                        @endif

                        <details class="mt-2">
                            <summary class="cursor-pointer text-sm text-blue-600">Ubah</summary>
                            <form method="POST" action="{{ route('experience.update', $experience) }}" class="mt-2 space-y-2">
                                @csrf
                                @method('PUT')
                                @include('profil._form-pengalaman', ['experience' => $experience])
                                <button type="submit"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700">
                                    Simpan perubahan
                                </button>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('experience.destroy', $experience) }}" class="mt-2"
                              onsubmit="return confirm('Hapus pengalaman ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 underline">Hapus</button>
                        </form>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">Belum ada pengalaman kerja.</li>
                @endforelse
            </ul>

            {{-- Form tambah disembunyikan kalau sudah mencapai batas --}}
            @if ($experiences->count() < \App\Models\WorkExperience::MAX_PER_PROFILE)
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-blue-600">+ Tambah pengalaman</summary>
                    <form method="POST" action="{{ route('experience.store') }}" class="mt-2 space-y-2">
                        @csrf
                        @include('profil._form-pengalaman')
                        <button type="submit"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700">
                            Tambah
                        </button>
                    </form>
                </details>
            @endif
        </section>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">Keluar</button>
        </form>
    </main>
</body>
</html>