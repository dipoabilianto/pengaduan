<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengguna</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

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

            <div class="mb-4 flex justify-end">
                <a href="{{ route('admin.users.create') }}" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                    + Tambah Pengguna
                </a>
            </div>

            <div class="rounded-lg bg-white shadow">
                <div class="overflow-x-auto rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Nama</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Email</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Role</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                                    <td class="px-4 py-3">
                                        @forelse ($user->roles as $role)
                                            <span @class([
                                                'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                'bg-sky-100 text-sky-700' => $role->name === 'superuser',
                                                'bg-gray-100 text-gray-600' => $role->name !== 'superuser',
                                            ])>{{ $role->name === 'superuser' ? 'Super User' : $role->name }}</span>
                                        @empty
                                            <span class="text-xs text-gray-400">Belum ada role</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-sky-600 hover:underline">Ubah</a>
                                        @unless ($user->id === auth()->id())
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Hapus pengguna {{ $user->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ms-3 font-medium text-rose-600 hover:underline">Hapus</button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">Belum ada pengguna.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
