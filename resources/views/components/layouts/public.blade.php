<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="{{ $faviconUrl ?? asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/chat-widget.js'])
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-950 via-blue-900 to-slate-900 text-slate-100 antialiased">
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-sky-500/20 blur-3xl"></div>
        <div class="absolute top-1/3 -right-24 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl"></div>
    </div>

    <header class="relative z-10 border-b border-white/10 bg-white/5 backdrop-blur-lg">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-y-2 px-3 py-3 sm:px-6 sm:py-4">
            <a href="{{ route('public.home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                @if ($logoUrl)
                    {{-- Tanpa badge kotak dibungkus: logo ditampilkan utuh sesuai rasio aslinya,
                         bukan dipaksa masuk kotak 1:1 yang bisa membuatnya tampak terpotong/gepeng. --}}
                    <img src="{{ $logoUrl }}" alt="Logo" class="h-8 w-auto shrink-0 object-contain sm:h-9">
                @else
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-sky-500/20 text-xs text-sky-300 sm:h-9 sm:w-9 sm:text-sm">KK</span>
                @endif
                <span class="text-xs leading-tight sm:text-sm">
                    KATA-KITA<br class="sm:hidden">
                    <span class="text-[10px] font-normal text-slate-300 sm:text-xs">Disdukcapil Tubaba</span>
                </span>
            </a>
            <div class="flex items-center gap-1 text-xs sm:gap-3 sm:text-sm">
                <a href="{{ route('public.home') }}#statistik" class="hidden rounded-lg px-3 py-2 text-slate-200 transition hover:bg-white/10 sm:inline-block">Statistik</a>
                <a href="{{ route('public.home') }}#daftar-pengaduan" class="hidden rounded-lg px-3 py-2 text-slate-200 transition hover:bg-white/10 sm:inline-block">Daftar Pengaduan</a>
                <a href="{{ route('public.status.check') }}" class="rounded-lg px-2 py-2 text-slate-200 transition hover:bg-white/10 sm:px-3">Cek Status</a>
                <a href="{{ route('report.create') }}" class="rounded-lg bg-sky-500 px-3 py-2 font-medium text-white shadow-lg shadow-sky-500/30 transition hover:bg-sky-400 sm:px-4">Buat Pengaduan</a>
            </div>
        </nav>
    </header>

    <main class="relative z-10">
        {{ $slot }}
    </main>

    <footer class="relative z-10 mt-20 border-t border-white/10 bg-white/5 py-8 text-center text-xs text-slate-400 backdrop-blur-lg">
        &copy; {{ date('Y') }} Dinas Kependudukan dan Pencatatan Sipil Kabupaten Tulang Bawang Barat.
        Data pelapor dilindungi &amp; dienkripsi.
    </footer>

    <x-chat-widget />
</body>
</html>
