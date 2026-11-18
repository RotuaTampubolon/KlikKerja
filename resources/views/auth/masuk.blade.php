<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - KlikKerja</title>
    {{-- Memuat CSS Tailwind hasil build Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <main class="w-full max-w-sm bg-white rounded-xl shadow p-6 text-center">
        <h1 class="text-2xl font-bold">KlikKerja</h1>
        <p class="mt-2 text-gray-600">Masuk untuk mencari atau menawarkan pekerjaan.</p>

        {{-- Pesan error dari controller (withErrors) --}}
        @error('google')
            <p class="mt-4 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <a href="{{ route('auth.google.redirect') }}"
           class="mt-6 inline-block w-full rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">
            Masuk dengan Google
        </a>
    </main>
</body>
</html>