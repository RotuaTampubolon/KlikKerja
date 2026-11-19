<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kode Verifikasi - KlikKerja</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <main class="w-full max-w-sm rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-bold">Kode verifikasi</h1>
        <p class="mt-1 text-sm text-gray-600">Masukkan kode 6 digit dari aplikasi authenticator Anda.</p>

        <form method="POST" action="{{ route('admin.2fa.store') }}" class="mt-6 space-y-4">
            @csrf

            {{-- inputmode numeric: keyboard angka di ponsel; one-time-code: bantu autofill --}}
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   autocomplete="one-time-code" required autofocus
                   class="w-full rounded-lg border p-2 text-center text-lg tracking-widest">

            @error('code')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="w-full rounded-lg bg-gray-900 px-4 py-2 font-medium text-white hover:bg-gray-700">
                Verifikasi
            </button>
        </form>
    </main>
</body>
</html>