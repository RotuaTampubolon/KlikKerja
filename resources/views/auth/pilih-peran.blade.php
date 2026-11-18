<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih Peran - KlikKerja</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 px-4 py-8 text-slate-900 sm:px-6 lg:px-8">
    <main class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-5xl items-center justify-center">
        <div class="grid w-full overflow-hidden rounded-3xl bg-white shadow-2xl shadow-slate-950/30 lg:grid-cols-[0.85fr_1.15fr]">
            <section class="relative hidden overflow-hidden bg-blue-600 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-cyan-300/20"></div>
                <div class="absolute -bottom-24 -left-20 h-64 w-64 rounded-full bg-indigo-950/20"></div>

                <div class="relative">
                    <div class="mb-12 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold ring-1 ring-white/25">
                            K
                        </div>
                        <span class="text-lg font-semibold tracking-tight">KlikKerja</span>
                    </div>
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-blue-100">Mulai perjalananmu</p>
                    <h2 class="max-w-sm text-4xl font-bold leading-tight tracking-tight">Temukan peluang yang tepat untukmu.</h2>
                </div>

                <p class="relative max-w-xs text-sm leading-6 text-blue-100">
                    Pilih peran untuk mendapatkan pengalaman KlikKerja yang sesuai dengan kebutuhanmu.
                </p>
            </section>

            <section class="p-6 sm:p-10">
                <div class="mb-8 lg:hidden">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white">
                            K
                        </div>
                        <span class="text-lg font-semibold tracking-tight">KlikKerja</span>
                    </div>
                </div>

                <div class="mb-8">
                    <p class="mb-2 text-sm font-semibold text-blue-600">Satu langkah lagi</p>
                    <h1 class="text-3xl font-bold tracking-tight text-slate-950">Pilih peran Anda</h1>
                    <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Peran hanya dapat dipilih satu kali dan tidak bisa diubah, jadi pilih yang paling sesuai.
                    </p>
                </div>

                <form method="POST" action="{{ route('role.store') }}" class="space-y-6">
                    {{-- Token anti-CSRF: wajib di setiap form POST Laravel --}}
                    @csrf

                    <fieldset>
                        <legend class="mb-3 text-sm font-semibold text-slate-700">Saya ingin bergabung sebagai</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="group relative flex cursor-pointer flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg hover:shadow-blue-500/10 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:ring-4 has-[:checked]:ring-blue-100">
                                <input type="radio" name="role" value="seeker" class="peer sr-only" @checked(old('role') === 'seeker')>
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-600 transition group-hover:bg-blue-600 group-hover:text-white peer-checked:bg-blue-600 peer-checked:text-white">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM3.75 20.25a7.5 7.5 0 0 1 16.5 0" />
                                    </svg>
                                </span>
                                <span>
                                    <span class="block font-semibold text-slate-900">Pencari Kerja</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">Temukan pekerjaan dan peluang baru.</span>
                                </span>
                                <span class="absolute right-4 top-4 hidden h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-white peer-checked:flex">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                                    </svg>
                                </span>
                            </label>

                            <label class="group relative flex cursor-pointer flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-500/10 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 has-[:checked]:ring-4 has-[:checked]:ring-emerald-100">
                                <input type="radio" name="role" value="employer" class="peer sr-only" @checked(old('role') === 'employer')>
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white peer-checked:bg-emerald-600 peer-checked:text-white">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 21V5.25A1.5 1.5 0 0 1 6.75 3.75h6a1.5 1.5 0 0 1 1.5 1.5V21m-5.25-9h3m-3 3h3m5.25-6.75h1.5a1.5 1.5 0 0 1 1.5 1.5V21" />
                                    </svg>
                                </span>
                                <span>
                                    <span class="block font-semibold text-slate-900">Pemberi Kerja</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">Temukan talenta terbaik untuk timmu.</span>
                                </span>
                                <span class="absolute right-4 top-4 hidden h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white peer-checked:flex">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                                    </svg>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    @error('role')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-500">
                        <input type="checkbox" name="consent" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500" @checked(old('consent'))>
                        <span>
                            Saya menyetujui
                            <a href="/syarat-ketentuan" target="_blank" class="font-medium text-blue-600 underline decoration-blue-200 underline-offset-2 hover:decoration-blue-600">Syarat dan Ketentuan</a>
                            serta
                            <a href="/kebijakan-privasi" target="_blank" class="font-medium text-blue-600 underline decoration-blue-200 underline-offset-2 hover:decoration-blue-600">Kebijakan Privasi</a>.
                        </span>
                    </label>

                    @error('consent')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-200">
                        Lanjutkan
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </button>
                </form>
            </section>
        </div>

         {{-- Logout memakai POST + token CSRF, bukan link biasa --}}
        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">Keluar</button>
        </form>
    </main>
</body>
</html>