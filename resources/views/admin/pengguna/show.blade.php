<x-admin-layout :title="$user->name">
    <section class="rounded-xl bg-white p-6 shadow">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 underline">&larr; Kembali</a>
        <h1 class="mt-2 text-xl font-bold">{{ $user->name }}</h1>
        <p class="text-sm text-gray-600">{{ $user->email }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
            <span>{{ $user->role?->label() ?? 'Belum memilih peran' }}</span>
            <x-verification-badge :status="$user->verification_status" />
            <span>
                Status akun:
                @if ($restriction?->type === \App\Enums\SanctionType::Ban)
                    <strong class="text-red-700">Diblokir</strong>
                @elseif ($restriction)
                    <strong class="text-yellow-700">
                        Ditangguhkan{{ $restriction->expires_at ? ' sampai '.$restriction->expires_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H.i').' WIB' : '' }}
                    </strong>
                @else
                    <strong class="text-green-700">Aktif</strong>
                @endif
            </span>
        </div>
    </section>

    {{-- Aksi. Tiap form hanya tampil kalau aksinya memang berlaku untuk status akun saat ini. --}}
    <section class="grid gap-4 md:grid-cols-2">
        <form method="POST" action="{{ route('admin.users.warn', $user) }}" class="space-y-2 rounded-xl bg-white p-4 shadow">
            @csrf
            <h2 class="font-semibold">Beri peringatan</h2>
            <textarea name="reason" rows="2" required maxlength="500" placeholder="Alasan"
                      class="w-full rounded-lg border p-2 text-sm"></textarea>
            <button type="submit" class="rounded-lg bg-gray-700 px-3 py-1.5 text-sm text-white">Kirim peringatan</button>
        </form>

        @if (! $restriction)
            <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-2 rounded-xl bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">Tangguhkan akun</h2>
                <textarea name="reason" rows="2" required maxlength="500" placeholder="Alasan"
                          class="w-full rounded-lg border p-2 text-sm"></textarea>
                <input type="number" name="days" min="1" max="365" required placeholder="Lama (hari)"
                       class="w-full rounded-lg border p-2 text-sm">
                <button type="submit" class="rounded-lg bg-yellow-600 px-3 py-1.5 text-sm text-white">Tangguhkan</button>
            </form>
        @endif

        @if ($restriction?->type !== \App\Enums\SanctionType::Ban)
            <form method="POST" action="{{ route('admin.users.ban', $user) }}" class="space-y-2 rounded-xl bg-white p-4 shadow"
                  onsubmit="return confirm('Blokir akun ini?')">
                @csrf
                <h2 class="font-semibold">Blokir akun</h2>
                <textarea name="reason" rows="2" required maxlength="500" placeholder="Alasan"
                          class="w-full rounded-lg border p-2 text-sm"></textarea>
                <button type="submit" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm text-white">Blokir</button>
            </form>
        @endif

        @if ($restriction)
            <form method="POST" action="{{ route('admin.users.restore', $user) }}" class="space-y-2 rounded-xl bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">Pulihkan akun</h2>
                <textarea name="reason" rows="2" required maxlength="500" placeholder="Alasan pemulihan"
                          class="w-full rounded-lg border p-2 text-sm"></textarea>
                <button type="submit" class="rounded-lg bg-green-600 px-3 py-1.5 text-sm text-white">Pulihkan</button>
            </form>
        @endif
    </section>

    <section class="rounded-xl bg-white p-6 shadow">
        <h2 class="text-lg font-semibold">Riwayat sanksi</h2>
        <ul class="mt-3 space-y-3 text-sm">
            @forelse ($sanctions as $sanction)
                <li class="rounded-lg border p-3">
                    <p>
                        <strong>{{ $sanction->type->label() }}</strong>
                        @if ($sanction->expires_at)
                            <span class="text-gray-500">(sampai {{ $sanction->expires_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H.i') }} WIB)</span>
                        @endif
                    </p>
                    <p class="text-gray-700">{{ $sanction->reason }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $sanction->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H.i') }} WIB
                        oleh {{ $sanction->admin?->name ?? '(Admin dihapus)' }}
                    </p>
                </li>
            @empty
                <li class="text-gray-500">Belum ada sanksi.</li>
            @endforelse
        </ul>
    </section>
</x-admin-layout>