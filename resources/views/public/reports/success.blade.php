<x-layouts.public :title="'Laporan Terkirim — KATA-KITA'">
    <section class="mx-auto max-w-xl px-6 py-20 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-6 text-2xl font-bold">Laporan Berhasil Dikirim</h1>
        <p class="mt-2 text-sm text-slate-300">Simpan nomor tiket berikut untuk memantau status laporan Anda.</p>

        <div class="mx-auto mt-6 inline-block rounded-2xl border border-white/10 bg-white/5 px-8 py-4 backdrop-blur-lg">
            <p class="text-xs uppercase tracking-widest text-slate-400">Nomor Tiket</p>
            <p class="mt-1 text-2xl font-bold tracking-widest text-sky-300">{{ $report->ticket_no }}</p>
        </div>

        <p class="mx-auto mt-4 max-w-sm text-xs text-slate-400">
            Nomor HP/WhatsApp yang Anda daftarkan (Personal Key) juga dapat digunakan untuk cek status kapan saja.
        </p>

        <div class="mt-8 flex justify-center gap-4">
            <a href="{{ route('public.status.check') }}" class="rounded-xl border border-white/20 bg-white/5 px-5 py-2.5 text-sm font-semibold hover:bg-white/10">Cek Status Sekarang</a>
            <a href="{{ route('public.home') }}" class="rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-400">Kembali ke Beranda</a>
        </div>
    </section>
</x-layouts.public>
