<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin - KlikKerja</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 p-4">
    <main class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-bold">Dashboard Admin</h1>
        {{-- Auth::guard('admin')->user() = Admin yang sedang login --}}
        <p class="mt-1 text-sm text-gray-600">Masuk sebagai {{ auth('admin')->user()->name }}</p>
            <a href="{{ route('admin.users.index') }}" class="mt-4 inline-block text-blue-600 underline">Kelola pengguna</a>

        <form method="POST" action="{{ route('admin.logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">Keluar</button>
        </form>
    </main>
</body>
</html>