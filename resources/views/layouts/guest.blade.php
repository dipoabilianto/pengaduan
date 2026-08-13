<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ $faviconUrl ?? asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen font-sans text-slate-100 antialiased">
        <div class="flex min-h-screen flex-col lg:flex-row">
            {{-- Left: animated brand panel, 70% — hidden on small screens where the split
                 layout has no room to breathe; the compact header below covers branding
                 there instead. --}}
            <div class="relative hidden overflow-hidden bg-gradient-to-br from-sky-950 via-blue-900 to-slate-900 lg:flex lg:w-[70%] lg:flex-col lg:items-center lg:justify-center">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="login-orb login-orb-a absolute -top-32 -left-20 h-[28rem] w-[28rem] rounded-full bg-sky-500/25 blur-3xl"></div>
                    <div class="login-orb login-orb-b absolute top-1/3 -right-24 h-[26rem] w-[26rem] rounded-full bg-indigo-500/20 blur-3xl"></div>
                    <div class="login-orb login-orb-c absolute bottom-0 left-1/4 h-[24rem] w-[24rem] rounded-full bg-cyan-400/15 blur-3xl"></div>
                    <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:3rem_3rem]"></div>
                </div>

                <div class="relative z-10 max-w-md px-10 text-center">
                    <div class="login-brand-in mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl border border-white/10 bg-white/10 shadow-2xl backdrop-blur">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="h-12 w-auto object-contain">
                        @else
                            <span class="text-2xl font-bold text-sky-300">KK</span>
                        @endif
                    </div>
                    <h1 class="login-brand-in text-3xl font-semibold tracking-tight text-white [animation-delay:.1s]">KATA-KITA</h1>
                    <p class="login-brand-in mt-3 text-sm leading-relaxed text-slate-300 [animation-delay:.2s]">
                        Kanal Terpadu Aspirasi dan Kritik Kita — Unit Layanan Pengaduan Disdukcapil
                        Kabupaten Tulang Bawang Barat.
                    </p>
                    <p class="login-brand-in mt-8 text-xs uppercase tracking-[0.2em] text-sky-300/70 [animation-delay:.3s]">Portal Internal Petugas</p>
                </div>
            </div>

            {{-- Right: login panel, 30% --}}
            <div class="flex flex-1 flex-col items-center justify-center bg-white px-6 py-10 lg:w-[30%] lg:flex-none lg:px-10">
                <a href="{{ route('public.home') }}" class="mb-8 flex items-center gap-2 lg:hidden">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo" class="h-9 w-auto object-contain">
                    @else
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-sm font-semibold text-sky-600">KK</span>
                    @endif
                    <span class="text-sm font-semibold leading-tight text-gray-800">
                        KATA-KITA<br>
                        <span class="text-xs font-normal text-gray-500">Portal Petugas</span>
                    </span>
                </a>

                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
