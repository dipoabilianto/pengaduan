<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengetahuan Asisten ULP</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Apa yang boleh dijawab AI di chat</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Asisten ULP (chat pelapor) hanya boleh menjawab pertanyaan umum dari daftar fakta di bawah ini —
                    jam layanan, syarat dokumen per layanan, cara cek status, dan semacamnya. Pertanyaan yang
                    jawabannya TIDAK ada di sini otomatis diteruskan ke petugas, bukan ditebak oleh AI. Isi
                    selengkap dan seakurat mungkin sesuai aturan resmi instansi — informasi di sini langsung
                    dipercaya oleh AI apa adanya.
                </p>

                <form method="POST" action="{{ route('admin.settings.chat-facts.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <textarea name="facts" rows="18" required
                                  class="w-full rounded-md border-gray-300 font-mono text-sm text-gray-900"
                                  placeholder="Contoh:&#10;Syarat perekaman KTP-el: sudah berusia 17 tahun/sudah menikah, membawa Kartu Keluarga asli, dan surat pengantar RT/RW.">{{ old('facts', $facts) }}</textarea>
                        @error('facts')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                        Simpan Pengetahuan
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
