<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Antrean Verifikasi - KlikKerja Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 p-4">
    <main class="mx-auto max-w-5xl rounded-xl bg-white p-6 shadow">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Antrean Verifikasi</h1>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-gray-500 underline hover:text-gray-700">Kembali ke dashboard</a>
        </div>

        {{-- Pesan sukses setelah menyetujui/menolak, dikirim controller lewat ->with('status', ...) --}}
        @if (session('status'))
            <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Tab filter. $tab = kode => label, $jumlah = kode => banyaknya. Tab aktif diberi warna gelap. --}}
        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Filter status">
            @foreach ($tab as $kode => $label)
                <a href="{{ route('admin.verifikasi.index', ['status' => $kode]) }}"
                   class="rounded-full px-4 py-1.5 text-sm font-medium {{ $filter === $kode ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $label }} ({{ $jumlah[$kode] }})
                </a>
            @endforeach
        </nav>

        @if ($pengajuan->isEmpty())
            {{-- Keadaan kosong: pesan ramah, bukan tabel kosong --}}
            <p class="mt-8 text-center text-sm text-gray-500">Tidak ada pengajuan pada kategori ini.</p>
        @else
            {{-- overflow-x-auto: tabel boleh digeser ke samping di layar sempit, tidak merusak tata letak --}}
            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Pengguna</th>
                            <th class="py-2 pr-4">Peran</th>
                            <th class="py-2 pr-4">Nomor WhatsApp</th>
                            <th class="py-2 pr-4">Dikirim</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pengajuan as $p)
                            <tr class="border-b last:border-0">
                                {{-- {{ }} otomatis meng-escape HTML, jadi nama yang berisi <script> tampil sebagai teks --}}
                                <td class="py-3 pr-4 font-medium">{{ $p->user->name }}</td>
                                <td class="py-3 pr-4">{{ $p->user->role?->value === 'employer' ? 'Pemberi Kerja' : 'Pencari Kerja' }}</td>
                                <td class="py-3 pr-4">{{ $p->phone_number }}</td>
                                {{-- diffForHumans() = "2 jam yang lalu"; title berisi waktu lengkap saat kursor diarahkan --}}
                                <td class="py-3 pr-4" title="{{ $p->submitted_at->format('d/m/Y H:i') }}">{{ $p->submitted_at->diffForHumans() }}</td>
                                <td class="py-3 pr-4">
                                    @php
                                        // Teks dan warna lencana status.
                                        $lencana = [
                                            'pending' => ['Menunggu', 'bg-amber-100 text-amber-800'],
                                            'verified' => ['Disetujui', 'bg-emerald-100 text-emerald-800'],
                                            'rejected' => ['Ditolak', 'bg-red-100 text-red-800'],
                                        ][$p->status->value];
                                    @endphp
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $lencana[1] }}">{{ $lencana[0] }}</span>
                                </td>
                                <td class="py-3 text-right">
                                    <a href="{{ route('admin.verifikasi.show', $p) }}" class="font-medium text-blue-600 underline hover:text-blue-800">Periksa</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tautan halaman 1, 2, 3, ... Parameter ?status= ikut terbawa berkat withQueryString() di controller --}}
            <div class="mt-5">{{ $pengajuan->links() }}</div>
        @endif
    </main>
</body>
</html>