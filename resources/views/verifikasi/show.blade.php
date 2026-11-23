<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Nomor WhatsApp - KlikKerja</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 px-4 py-8 text-slate-900 sm:px-6">
    <main class="mx-auto w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl shadow-slate-950/30 sm:p-10">
        <p class="mb-2 text-sm font-semibold text-blue-600">Verifikasi akun</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-950">Verifikasi nomor WhatsApp</h1>

        {{-- Pesan sukses (mis. "Kode telah dikirim"), dikirim controller lewat ->with('status', ...) --}}
        @if (session('status'))
        <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
            {{ session('status') }}
        </div>
        @endif

        {{-- Pesan penolakan dari OtpService (terlalu cepat, kode salah, dst), kunci error 'otp' --}}
        @error('otp')
        <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ $message }}
        </div>
        @enderror

        {{-- Penolakan pengiriman dokumen dari VerificationService (OTP belum selesai, sedang diperiksa, dst), kunci error 'dokumen' --}}
        @error('dokumen')
        <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ $message }}
        </div>
        @enderror

        @if ($sedangDiperiksa)
        {{-- KONDISI 1: dokumen sudah dikirim, tinggal menunggu Admin --}}
        <p class="mt-6 text-sm leading-6 text-slate-600">
            Dokumen Anda sedang diperiksa oleh Admin. Kami akan memberi tahu Anda setelah pemeriksaan selesai.
        </p>

        @elseif ($nomorTerverifikasi)
        {{-- KONDISI 2: nomor sudah lolos OTP, saatnya mengunggah dokumen identitas --}}
        <p class="mt-6 text-sm leading-6 text-slate-600">
            Nomor <strong class="text-slate-900">{{ $nomorTerverifikasi }}</strong> sudah terverifikasi.
        </p>

        {{-- Jika pengajuan sebelumnya ditolak, tampilkan alasannya agar pengguna tahu apa yang diperbaiki --}}
        @if ($alasanPenolakan)
        <div class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
            <p class="font-semibold">Verifikasi sebelumnya ditolak</p>
            <p class="mt-1">{{ $alasanPenolakan }}</p>
            <p class="mt-1">Silakan unggah ulang dokumen Anda.</p>
        </div>
        @endif

        <h2 class="mt-6 text-lg font-semibold text-slate-950">Unggah dokumen identitas</h2>
        {{-- Penjelasan tujuan pengumpulan data (PRD 12.2) --}}
        <p class="mt-1 text-sm leading-6 text-slate-500">
            Foto KTP dan selfie hanya dipakai untuk memeriksa identitas Anda. Dokumen disimpan tertutup
            dan hanya dapat dilihat Admin yang berwenang.
        </p>

        {{-- enctype multipart/form-data WAJIB agar browser mengirim isi file --}}
        <form method="POST" action="{{ route('verifikasi.dokumen.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="ktp" class="block text-sm font-medium text-slate-700">Foto KTP</label>
                {{-- accept hanya membantu pengguna memilih file; pemeriksaan sebenarnya ada di server --}}
                <input id="ktp" type="file" name="ktp" accept="image/jpeg,image/png" required
                    class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                @error('ktp')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="selfie" class="block text-sm font-medium text-slate-700">Selfie memegang KTP</label>
                <input id="selfie" type="file" name="selfie" accept="image/jpeg,image/png" required
                    class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                @error('selfie')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <p class="text-xs leading-5 text-slate-500">
                Format JPG, JPEG, atau PNG. Maksimal 4 MB per file. Pastikan tulisan pada KTP terbaca jelas.
            </p>

            <button type="submit"
                class="w-full rounded-xl bg-slate-950 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-200">
                Kirim dokumen
            </button>
        </form>

        @elseif ($nomorAktif)
        {{-- KONDISI 3: kode sudah dikirim, pengguna diminta mengetik kode --}}
        <h2 class="mt-6 text-lg font-semibold text-slate-950">Masukkan kode verifikasi</h2>
        <p class="mt-1 text-sm leading-6 text-slate-500">
            Kode 6 angka telah dikirim ke <strong class="text-slate-900">{{ $nomorAktif }}</strong>
            dan berlaku 5 menit.
        </p>

        <form method="POST" action="{{ route('verifikasi.otp.cek') }}" class="mt-5 space-y-4">
            {{-- Token anti-CSRF: wajib di setiap form POST Laravel --}}
            @csrf
            {{-- Nomor dikirim kembali. Aman diubah-ubah: OtpService hanya mengenali pasangan (akun ini, nomor ini). --}}
            <input type="hidden" name="nomor" value="{{ $nomorAktif }}">

            <div>
                <label for="kode" class="block text-sm font-medium text-slate-700">Kode verifikasi</label>
                {{-- inputmode numeric = keyboard angka di HP; one-time-code = browser menawarkan isi otomatis --}}
                <input id="kode" type="text" name="kode" inputmode="numeric" autocomplete="one-time-code"
                    maxlength="7" required autofocus placeholder="123456"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-lg tracking-widest focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100">
                {{-- Pesan validasi Form Request (mis. kode bukan 6 angka) --}}
                @error('kode')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-slate-950 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-200">
                Verifikasi
            </button>
        </form>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            {{-- Kirim ulang = form kecil yang memanggil endpoint kirim dengan nomor yang sama --}}
            <form method="POST" action="{{ route('verifikasi.otp.kirim') }}">
                @csrf
                <input type="hidden" name="nomor" value="{{ $nomorAktif }}">
                <button type="submit" class="font-medium text-blue-600 underline underline-offset-2 hover:text-blue-800">
                    Kirim ulang kode
                </button>
            </form>

            <a href="{{ route('verifikasi.show', ['ganti' => 1]) }}" class="text-slate-500 underline underline-offset-2 hover:text-slate-700">
                Gunakan nomor lain
            </a>
        </div>
        @else
        {{-- KONDISI 4: belum ada nomor, tampilkan form nomor --}}
        <p class="mt-1 text-sm leading-6 text-slate-500">
            Kami akan mengirim kode 6 angka ke WhatsApp Anda untuk memastikan nomor ini milik Anda.
        </p>

        <form method="POST" action="{{ route('verifikasi.otp.kirim') }}" class="mt-5 space-y-4">
            @csrf

            <div>
                <label for="nomor" class="block text-sm font-medium text-slate-700">Nomor WhatsApp</label>
                {{-- old('nomor') mengisi ulang isian jika validasi gagal --}}
                <input id="nomor" type="tel" name="nomor" value="{{ old('nomor') }}" required autofocus
                    placeholder="0812 3456 7890"
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100">
                @error('nomor')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-slate-950 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-200">
                Kirim kode
            </button>
        </form>
        @endif

        {{-- Logout memakai POST + token CSRF, bukan link biasa (sama seperti halaman pilih peran) --}}
        <form method="POST" action="{{ route('logout') }}" class="mt-8 text-center">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">Keluar</button>
        </form>
    </main>
</body>

</html>