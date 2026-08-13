<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manajemen Laporan</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700 border border-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mb-4 flex justify-end">
                <a href="{{ route('admin.reports.export', $filters) }}" class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Unduh Excel
                </a>
            </div>

            @php
                // Status sekarang sudah punya jalan pintas navigasi sendiri di sidebar —
                // dropdown status di sini tetap ada tapi disembunyikan bareng filter
                // lanjutan lain, karena kegunaannya kini spesifik: menggabungkan status
                // dengan filter lain dalam satu pencarian (sidebar hanya bisa satu-satu).
                $advancedKeys = ['status', 'urgency_flag', 'channel', 'date_from', 'date_to'];
                $activeAdvancedCount = collect($advancedKeys)->filter(fn ($key) => filled($filters[$key] ?? null))->count();
            @endphp

            <form method="GET" x-data="{ expanded: false }" class="mb-6 rounded-lg bg-white p-4 shadow">
                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="Cari kategori..." class="w-full rounded-md border-gray-300 text-sm sm:w-56">

                    <select name="type" class="w-full rounded-md border-gray-300 text-sm sm:w-44">
                        <option value="">Semua Jenis</option>
                        <option value="pengaduan" @selected(($filters['type'] ?? '') === 'pengaduan')>Pengaduan</option>
                        <option value="whistleblowing" @selected(($filters['type'] ?? '') === 'whistleblowing')>Whistleblowing</option>
                    </select>

                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Filter</button>
                    <a href="{{ route('admin.reports.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>

                    <button type="button" @click="expanded = ! expanded" class="ms-auto inline-flex items-center gap-1.5 text-sm font-medium text-sky-600 hover:text-sky-700">
                        <span x-text="expanded ? 'Sembunyikan filter lanjutan' : 'Filter lanjutan'">Filter lanjutan</span>
                        @if ($activeAdvancedCount > 0)
                            <span class="rounded-full bg-sky-100 px-1.5 py-0.5 text-xs font-semibold text-sky-700">{{ $activeAdvancedCount }}</span>
                        @endif
                        <svg class="h-4 w-4 shrink-0 transition-transform duration-150" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </div>

                <div x-show="expanded" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-3 grid grid-cols-2 gap-3 border-t border-gray-100 pt-3 sm:grid-cols-3 lg:grid-cols-5">
                    <select name="status" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Semua Status</option>
                        @foreach (\App\Models\Report::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="urgency_flag" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Semua Urgensi</option>
                        @foreach (\App\Models\Report::URGENCY_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['urgency_flag'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="channel" class="w-full rounded-md border-gray-300 text-sm">
                        <option value="">Semua Kanal</option>
                        @foreach (['web' => 'Web', 'whatsapp' => 'WhatsApp', 'sosmed' => 'Sosial Media'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['channel'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-md border-gray-300 text-sm">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-md border-gray-300 text-sm">
                </div>
            </form>

            <div class="rounded-lg bg-white shadow">
              <div class="overflow-x-auto rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Tiket</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Jenis / Kategori</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Urgensi</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Kanal</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Tanggal</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reports as $report)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $report->ticket_no }}</td>
                                <td class="px-4 py-3">
                                    <span class="block font-medium capitalize">{{ $report->type }}</span>
                                    <span class="text-xs text-gray-500">{{ $report->category }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium">{{ $report->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($report->urgency_flag)
                                        <x-urgency-badge :flag="$report->urgency_flag" />
                                    @else
                                        <span class="text-xs text-gray-400">Belum dinilai</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 capitalize">{{ $report->channel }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $report->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{-- Query string filter saat ini ikut dibawa ke halaman detail —
                                         bukan supaya controller-nya memakainya, tapi supaya sidebar
                                         (yang membaca request('status') dari URL mana pun sedang
                                         dibuka) tetap menyorot filter yang sama, bukan balik ke
                                         "Semua" begitu admin masuk ke satu laporan dari daftar
                                         yang sudah difilter. --}}
                                    <a href="{{ route('admin.reports.show', $report) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}" class="font-medium text-sky-600 hover:underline">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada laporan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
              </div>
            </div>

            <div class="mt-4">
                {{ $reports->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
