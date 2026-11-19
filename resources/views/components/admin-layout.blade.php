@props(['title' => 'Admin'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Admin KlikKerja</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50">
    <header class="bg-gray-900 text-white">
        <nav class="mx-auto flex max-w-5xl items-center gap-4 p-4 text-sm">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold">Admin KlikKerja</a>
            <a href="{{ route('admin.users.index') }}" class="text-gray-300 hover:text-white">Pengguna</a>
            <form method="POST" action="{{ route('admin.logout') }}" class="ml-auto">
                @csrf
                <button type="submit" class="text-gray-300 underline hover:text-white">Keluar</button>
            </form>
        </nav>
    </header>

    <main class="mx-auto max-w-5xl space-y-6 p-4">
        @if (session('status'))
            <p class="rounded-lg bg-green-100 p-3 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul class="list-disc rounded-lg bg-red-100 p-3 pl-8 text-sm text-red-800">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        {{ $slot }}
    </main>
</body>
</html>