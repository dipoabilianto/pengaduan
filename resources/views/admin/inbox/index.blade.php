<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inbox Terpadu</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <p class="mb-4 text-sm text-gray-500">
                Pesan WhatsApp &amp; Instagram (DM, komentar, mention) yang belum berupa laporan resmi (Bab 6.2 &amp; 7
                PDR). Konversi pesan yang relevan menjadi laporan, abaikan yang bukan pengaduan, atau balas langsung
                (khusus Instagram).
            </p>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mb-4 flex gap-2">
                @foreach (['baru' => 'Baru', 'dikonversi' => 'Dikonversi', 'diabaikan' => 'Diabaikan', 'semua' => 'Semua'] as $key => $label)
                    <a href="{{ route('admin.inbox.index', ['status' => $key]) }}"
                       @class([
                           'rounded-md px-3 py-1.5 text-xs font-medium',
                           'bg-sky-600 text-white' => $currentStatus === $key,
                           'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' => $currentStatus !== $key,
                       ])>
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Sumber</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Dari</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Isi Pesan</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Diterima</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr x-data="{ replying: false, message: '' }">
                                <td class="px-4 py-3">
                                    <span class="capitalize">{{ $item->source }}</span>
                                    @if ($item->source === 'instagram' && $item->external_type)
                                        <span @class([
                                            'ml-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            'bg-fuchsia-100 text-fuchsia-700' => $item->external_type === 'message',
                                            'bg-indigo-100 text-indigo-700' => $item->external_type === 'comment',
                                        ])>
                                            {{ $item->external_type === 'message' ? 'DM' : 'Komentar' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $item->external_ref ?? '-' }}</td>
                                <td class="px-4 py-3 max-w-md truncate">{{ $item->raw_message }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-sky-100 text-sky-700' => $item->status === 'baru',
                                        'bg-emerald-100 text-emerald-700' => $item->status === 'dikonversi',
                                        'bg-gray-100 text-gray-600' => $item->status === 'diabaikan',
                                        'bg-amber-100 text-amber-700' => $item->status === 'ditriase',
                                    ])>
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('manage', $item)
                                        @if (in_array($item->status, ['baru', 'ditriase'], true))
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @if ($item->source === 'instagram')
                                                    <button type="button" @click="replying = ! replying" class="rounded-md border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Balas</button>
                                                @endif
                                                <form method="POST" action="{{ route('admin.inbox.convert', $item) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500">Konversi ke Laporan</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.inbox.ignore', $item) }}" class="inline" onsubmit="return confirm('Abaikan pesan ini?');">
                                                    @csrf
                                                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Abaikan</button>
                                                </form>
                                            </div>
                                            @if ($item->source === 'instagram')
                                                <form method="POST" action="{{ route('admin.inbox.reply', $item) }}" x-show="replying" x-cloak class="mt-2 flex items-start justify-end gap-2">
                                                    @csrf
                                                    <textarea name="message" x-model="message" rows="2" maxlength="1000" placeholder="Tulis balasan..." class="w-64 rounded-md border-gray-300 text-xs" required></textarea>
                                                    <button type="submit" class="shrink-0 rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500">Kirim</button>
                                                </form>
                                            @endif
                                        @elseif ($item->linkedReport)
                                            <a href="{{ route('admin.reports.show', $item->linkedReport) }}" class="text-sky-600 hover:underline">Lihat Laporan</a>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada pesan masuk.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
