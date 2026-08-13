<x-layouts.public :title="'Cek Status Laporan — KATA-KITA'">
    <section class="mx-auto max-w-md px-6 py-20">
        <h1 class="text-center text-2xl font-bold">Cek Status Laporan</h1>
        <p class="mt-2 text-center text-sm text-slate-300">
            Masukkan Nomor Tiket atau Nomor HP (Personal Key) yang Anda gunakan saat melapor.
        </p>

        <form method="GET" action="{{ route('public.status.result') }}" class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
            <label class="block text-sm font-medium text-slate-200">Nomor Tiket / Nomor HP</label>
            <input type="text" name="query" required value="{{ old('query') }}"
                   class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400"
                   placeholder="WBS-20260810-XXXXXX atau 08xxxxxxxxxx">

            @error('query')
                <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-4 w-full rounded-lg bg-sky-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-400">
                Cek Status
            </button>
        </form>
    </section>
</x-layouts.public>
