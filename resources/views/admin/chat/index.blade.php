<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Live Chat</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div id="chat-inbox-update-banner" class="mb-4 hidden rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-700">
                Ada pembaruan tiket baru.
                <a href="{{ route('admin.chat.index') }}" class="font-medium underline">Muat ulang</a>
            </div>

            <div class="mb-4 rounded-lg bg-white p-4 shadow">
                <h3 class="text-sm font-medium text-gray-500">Rata-rata Kepuasan (IKM)</h3>
                @if ($ratingSummary['total'] > 0)
                    <p class="mt-1 text-2xl font-semibold text-gray-800">
                        {{ str_replace('.', ',', (string) $ratingSummary['average']) }} / 4
                        <span class="text-sm font-normal text-gray-400">dari {{ $ratingSummary['total'] }} penilaian</span>
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                        @foreach (\App\Models\ChatRating::SCALE_LABELS as $scale => $label)
                            <span>{{ \App\Models\ChatRating::SCALE_EMOJI[$scale] }} {{ $label }}: {{ $ratingSummary['breakdown'][$scale] ?? 0 }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-1 text-sm text-gray-400">Belum ada penilaian dari pelapor.</p>
                @endif
            </div>

            <div class="rounded-lg bg-white shadow">
                <div class="overflow-x-auto rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pesan Terakhir</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Petugas</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Waktu</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($tickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-amber-100 text-amber-700' => $ticket->status === 'menunggu_respon',
                                            'bg-sky-100 text-sky-700' => $ticket->status === 'sedang_ditangani',
                                            'bg-emerald-100 text-emerald-700' => $ticket->status === 'selesai',
                                        ])>{{ $ticket->statusLabel() }}</span>
                                        @if ($ticket->pending_escalation_count > 0)
                                            <span class="ml-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">Butuh Petugas</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 max-w-xs truncate text-gray-600">{{ $ticket->last_message_preview ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $ticket->assignedOfficer?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-400">{{ $ticket->last_message_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.chat.show', $ticket) }}" class="font-medium text-sky-600 hover:underline">Buka</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada tiket chat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $tickets->links() }}
            </div>
        </div>
    </div>

    @vite('resources/js/admin-chat.js')
</x-app-layout>
