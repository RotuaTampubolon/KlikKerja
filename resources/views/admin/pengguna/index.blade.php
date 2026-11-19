<x-admin-layout title="Pengguna">
    <section class="rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-bold">Pengguna</h1>

        <form method="GET" action="{{ route('admin.users.index') }}" class="mt-4 flex gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama atau email"
                   class="w-full rounded-lg border p-2 text-sm">
            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white">Cari</button>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-500">
                    <tr>
                        <th class="py-2">Nama</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Verifikasi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b">
                            <td class="py-2">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role?->label() ?? 'Belum memilih' }}</td>
                            <td><x-verification-badge :status="$user->verification_status" /></td>
                            <td>
                                <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">Tidak ada pengguna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    </section>
</x-admin-layout>