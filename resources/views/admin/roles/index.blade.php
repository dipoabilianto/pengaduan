<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Peran &amp; Izin</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    {{ session('error') }}
                </div>
            @endif

            <p class="mb-4 text-sm text-gray-500">
                Setiap role bebas diatur: kemampuan apa saja yang dimiliki, dan status laporan mana yang boleh dilihat.
                Role baru tidak melihat laporan apa pun sampai statusnya dicentang secara sadar.
            </p>

            <div class="mb-4 flex justify-end">
                <a href="{{ route('admin.roles.create') }}" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                    + Tambah Role
                </a>
            </div>

            <div class="rounded-lg bg-white shadow">
                <div class="overflow-x-auto rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Role</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Izin</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pengguna</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($roles as $role)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-800">
                                        {{ $role->name === 'superuser' ? 'Super User' : $role->name }}
                                        @if ($role->name === 'superuser')
                                            <span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Sistem</span>
                                        @elseif ($role->permissions_count === 0)
                                            <span class="ms-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Belum diatur</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $role->name === 'superuser' ? 'Semua (tanpa batasan)' : $role->permissions_count.' izin' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $role->users_count }} pengguna</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($role->name === 'superuser')
                                            <span class="text-xs text-gray-400">Dikunci</span>
                                        @else
                                            <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium text-sky-600 hover:underline">Ubah</a>
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Hapus role {{ $role->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ms-3 font-medium text-rose-600 hover:underline">Hapus</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
