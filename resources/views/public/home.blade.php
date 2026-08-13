@php
    $chartPayload = [
        'monthly' => $monthly,
        'categories' => [
            'labels' => $topCategories->pluck('category'),
            'counts' => $topCategories->pluck('total'),
        ],
        'status' => [
            'labels' => $statusDistribution->pluck('status'),
            'counts' => $statusDistribution->pluck('total'),
        ],
    ];
@endphp

<x-layouts.public :title="'KATA-KITA — Kanal Terpadu Aspirasi dan Kritik Kita'">
    <script id="dashboard-data" type="application/json">{!! json_encode($chartPayload) !!}</script>

    <section class="mx-auto max-w-6xl px-6 pb-16 pt-20 text-center">
        <p data-aos="fade-down" class="inline-block rounded-full border border-white/10 bg-white/5 px-4 py-1 text-xs font-medium tracking-wide text-sky-300">
            Dinas Kependudukan dan Pencatatan Sipil Kabupaten Tulang Bawang Barat
        </p>

        <h1 data-aos="fade-up" class="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">
            KATA-KITA
        </h1>
        <p data-aos="fade-up" class="mt-2 text-sm font-medium tracking-wide text-sky-300">
            Kanal Terpadu Aspirasi dan Kritik Kita
        </p>

        <p data-aos="fade-up" data-aos-delay="100" class="mx-auto mt-4 max-w-2xl text-slate-300">
            Sampaikan pengaduan pelayanan publik atau laporkan dugaan pelanggaran secara aman dan rahasia.
            Tanpa perlu registrasi akun — identitas Anda dilindungi dan dienkripsi.
        </p>

        <div data-aos="fade-up" data-aos-delay="200" class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('report.create') }}"
               class="rounded-xl bg-sky-500 px-6 py-3 font-semibold text-white shadow-lg shadow-sky-500/30 transition hover:bg-sky-400">
                Buat Pengaduan
            </a>
            <a href="{{ route('public.status.check') }}"
               class="rounded-xl border border-white/20 bg-white/5 px-6 py-3 font-semibold text-slate-100 backdrop-blur transition hover:bg-white/10">
                Cek Status Laporan
            </a>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-6 pb-20">
        <div class="grid gap-6 sm:grid-cols-3">
            <div data-aos="fade-up" class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-lg shadow-xl">
                <p class="text-3xl font-bold text-sky-300">{{ $stats['total'] }}</p>
                <p class="mt-1 text-sm text-slate-300">Total Laporan Masuk</p>
            </div>
            <div data-aos="fade-up" data-aos-delay="100" class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-lg shadow-xl">
                <p class="text-3xl font-bold text-emerald-300">{{ $stats['selesai'] }}</p>
                <p class="mt-1 text-sm text-slate-300">Selesai Ditangani</p>
            </div>
            <div data-aos="fade-up" data-aos-delay="200" class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center backdrop-blur-lg shadow-xl">
                <p class="text-3xl font-bold text-amber-300">{{ $stats['dalam_proses'] }}</p>
                <p class="mt-1 text-sm text-slate-300">Dalam Proses</p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-6 pb-24">
        <div data-aos="fade-up" class="grid gap-6 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
                <h3 class="font-semibold text-slate-100">1. Isi Laporan</h3>
                <p class="mt-2 text-sm text-slate-300">Pilih jenis pelaporan, isi kronologi 5W1H, dan lampirkan bukti pendukung bila ada.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
                <h3 class="font-semibold text-slate-100">2. Dapatkan Nomor Tiket</h3>
                <p class="mt-2 text-sm text-slate-300">Nomor HP Anda menjadi Personal Key (PK) untuk memantau status tanpa perlu login.</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
                <h3 class="font-semibold text-slate-100">3. Pantau Tindak Lanjut</h3>
                <p class="mt-2 text-sm text-slate-300">Laporan ditindaklanjuti tim Disdukcapil sesuai tingkat urgensi dan SLA yang berlaku.</p>
            </div>
        </div>
    </section>

    {{-- Statistik Publik (Bab 4.4) --}}
    <section id="statistik" class="mx-auto max-w-6xl scroll-mt-24 px-6 pb-20">
        <h2 class="text-center text-2xl font-bold sm:text-3xl">Dashboard Statistik Publik</h2>
        <p class="mx-auto mt-2 max-w-2xl text-center text-sm text-slate-300">
            Data agregat pengaduan &amp; whistleblowing. Tidak ada data individual atau identitas pelapor yang ditampilkan.
        </p>

        <div class="mt-6 flex justify-center">
            <div class="rounded-2xl border border-white/10 bg-white/5 px-8 py-4 text-center backdrop-blur-lg">
                <p class="text-3xl font-bold text-sky-300">{{ $publicTotal }}</p>
                <p class="mt-1 text-sm text-slate-300">Total Laporan (publik)</p>
            </div>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
                <h3 class="font-semibold">Jumlah Laporan per Bulan</h3>
                <div class="mt-4 h-64"><canvas id="chart-monthly"></canvas></div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
                <h3 class="font-semibold">Kategori Terbanyak (Top Issues)</h3>
                <div class="mt-4 h-64"><canvas id="chart-categories"></canvas></div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg lg:col-span-2">
                <h3 class="font-semibold">Distribusi Status Penanganan</h3>
                <div class="mx-auto mt-4 h-72 max-w-md"><canvas id="chart-status"></canvas></div>
            </div>
        </div>
    </section>

    {{-- Daftar Pengaduan Publik (Bab 4.3) --}}
    <section id="daftar-pengaduan" class="mx-auto max-w-4xl scroll-mt-24 px-6 pb-24">
        <h2 class="text-center text-2xl font-bold sm:text-3xl">Daftar Pengaduan Publik</h2>
        <p class="mx-auto mt-2 max-w-2xl text-center text-sm text-slate-300">
            Ringkasan status penanganan. Identitas pelapor &amp; detail sensitif tidak pernah ditampilkan.
            Laporan berflag Red Code disembunyikan dari daftar ini.
        </p>

        {{-- Filter & daftar dimuat lewat fetch(), bukan submit form biasa — form GET biasa
             memicu reload halaman PENUH, yang bikin scroll balik ke atas dulu (memutar ulang
             animasi AOS di seluruh halaman) sebelum melompat lagi ke #daftar-pengaduan. Efeknya
             terlihat seperti "flicker" yang mengganggu setiap kali Filter/paginasi diklik. --}}
        <div x-data="{
                loading: false,
                loadList(url) {
                    this.loading = true;
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then((res) => res.text())
                        .then((html) => {
                            document.getElementById('report-list-container').innerHTML = html;
                            history.pushState({}, '', url);
                        })
                        .catch(() => { window.location.href = url; })
                        .finally(() => { this.loading = false; });
                },
                init() {
                    window.addEventListener('popstate', () => this.loadList(window.location.href));
                },
             }">
            <form @submit.prevent="loadList('{{ route('public.home') }}?' + new URLSearchParams(new FormData($el)).toString() + '#daftar-pengaduan')"
                  class="mt-8 flex flex-wrap gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-lg">
                <select name="status" class="rounded-lg border-white/15 bg-white/10 text-sm text-slate-100">
                    <option value="" class="text-slate-900">Semua Status</option>
                    @foreach (\App\Models\Report::PUBLIC_STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" class="text-slate-900" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="category" class="rounded-lg border-white/15 bg-white/10 text-sm text-slate-100">
                    <option value="" class="text-slate-900">Semua Kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" class="text-slate-900" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>

                <button type="submit" :disabled="loading" class="rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-400 disabled:opacity-60">
                    <span x-text="loading ? 'Memuat...' : 'Filter'">Filter</span>
                </button>
                <a href="{{ route('public.home') }}#daftar-pengaduan" @click.prevent="loadList('{{ route('public.home') }}#daftar-pengaduan')" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">Reset</a>
            </form>

            {{-- Delegasi klik: tautan paginasi ada di dalam partial yang di-render server-side
                 setiap fetch, jadi tidak bisa dipasangi listener satu-satu — cukup satu listener
                 di container yang menangkap klik ke elemen <a> mana pun di dalamnya. --}}
            <div id="report-list-container"
                 :class="{ 'opacity-50': loading }" class="transition-opacity duration-150"
                 @click="if ($event.target.closest('a')) { $event.preventDefault(); loadList($event.target.closest('a').href); }">
                @include('public.partials.report-list')
            </div>
        </div>
    </section>

    @vite('resources/js/dashboard.js')
</x-layouts.public>
