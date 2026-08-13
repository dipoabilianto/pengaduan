@php
    $statusLabels = collect(\App\Models\Report::STATUS_LABELS);

    // Status dengan jumlah 0 disaring dari grafik/legenda — sama seperti Distribusi
    // Urgensi di bawah — supaya legenda tidak "menumpuk" menampilkan status yang
    // memang sedang tidak ada datanya sama sekali. Kunci status (bukan cuma posisi
    // array) ikut dikirim ke JS supaya warna tiap status tetap konsisten walau
    // sebagian disaring (lihat resources/js/admin-dashboard.js: statusColors).
    $statusChartEntries = $statusLabels->keys()
        ->map(fn ($key) => ['key' => $key, 'label' => $statusLabels[$key], 'count' => $statusCounts[$key]])
        ->filter(fn (array $row) => $row['count'] > 0)
        ->values();

    $chartPayload = [
        'monthly' => $monthly,
        'status' => [
            'keys' => $statusChartEntries->pluck('key'),
            'labels' => $statusChartEntries->pluck('label'),
            'counts' => $statusChartEntries->pluck('count'),
        ],
        'categories' => [
            'labels' => $topCategories->pluck('category'),
            'counts' => $topCategories->pluck('total'),
        ],
        'urgency' => [
            'labels' => $urgencyDistribution->pluck('label'),
            'counts' => $urgencyDistribution->pluck('total'),
            'flags' => $urgencyDistribution->pluck('flag'),
        ],
    ];

    // Kelas Tailwind ditulis lengkap (bukan disusun dari string dinamis) supaya tetap
    // terdeteksi oleh content scanner Tailwind saat build — kelas yang dirakit dari
    // interpolasi variabel PHP tidak akan pernah muncul di CSS hasil build.
    $tiles = [
        ['label' => 'Total Laporan', 'value' => $stats['total'], 'classes' => 'bg-sky-100 text-sky-600', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['label' => 'Baru Masuk', 'value' => $stats['baru_masuk'], 'classes' => 'bg-sky-100 text-sky-600', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['label' => 'Dalam Proses', 'value' => $stats['dalam_proses'], 'classes' => 'bg-status-warning/10 text-status-warning', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Selesai', 'value' => $stats['selesai'], 'classes' => 'bg-status-good/10 text-status-good', 'icon' => 'M5 13l4 4L19 7'],
        ['label' => 'Ditolak', 'value' => $stats['ditolak'], 'classes' => 'bg-status-critical/10 text-status-critical', 'icon' => 'M6 18L18 6M6 6l12 12'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <script id="admin-dashboard-data" type="application/json">{!! json_encode($chartPayload) !!}</script>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <!-- Stat tiles -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($tiles as $tile)
                    <div class="rounded-lg bg-white p-4 shadow">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $tile['classes'] }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tile['icon'] }}" />
                            </svg>
                        </span>
                        <p class="mt-3 text-2xl font-semibold text-gray-800">{{ $tile['value'] }}</p>
                        <p class="text-xs text-gray-500">{{ $tile['label'] }}</p>
                    </div>
                @endforeach
            </div>

            <!-- Grafik -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-700">Laporan per Bulan</h3>
                    <div class="mt-4 h-64"><canvas id="chart-monthly"></canvas></div>
                </div>
                @if ($statusChartEntries->isNotEmpty())
                    <div class="rounded-lg bg-white p-5 shadow">
                        <h3 class="text-sm font-semibold text-gray-700">Distribusi Status</h3>
                        <div class="mt-4 h-64"><canvas id="chart-status"></canvas></div>
                    </div>
                @endif
                <div class="rounded-lg bg-white p-5 shadow">
                    <h3 class="text-sm font-semibold text-gray-700">Top 5 Kategori</h3>
                    <div class="mt-4 h-64"><canvas id="chart-categories"></canvas></div>
                </div>
                @if ($urgencyDistribution->isNotEmpty())
                    <div class="rounded-lg bg-white p-5 shadow">
                        <h3 class="text-sm font-semibold text-gray-700">Distribusi Urgensi</h3>
                        <div class="mt-4 h-64"><canvas id="chart-urgency"></canvas></div>
                    </div>
                @endif

                <x-ai-status-detail-card :status="$aiStatus" />
            </div>
        </div>
    </div>

    @vite('resources/js/admin-dashboard.js')
</x-app-layout>
