<x-layouts.public :title="'Hasil Cek Status — KATA-KITA'">
    <section class="mx-auto max-w-2xl px-6 py-20">
        <h1 class="text-center text-2xl font-bold">Hasil Pencarian</h1>
        <p class="mt-2 text-center text-sm text-slate-300">Kata kunci: <span class="font-medium text-slate-100">{{ $query }}</span></p>

        <div class="mt-8 space-y-4">
            @forelse ($reports as $report)
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-lg">
                    <div class="flex items-center justify-between">
                        <p class="font-mono text-sm font-semibold text-sky-300">{{ $report->ticket_no }}</p>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium">
                            {{ $report->publicStatusLabel() }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-slate-200">{{ $report->category }}</p>
                    <p class="mt-1 text-xs capitalize text-slate-400">
                        {{ $report->type === 'whistleblowing' ? 'Whistleblowing' : 'Pengaduan Pelayanan Publik' }}
                        &middot; Dilaporkan {{ $report->created_at->translatedFormat('d M Y') }}
                    </p>

                    <div class="mt-6 border-t border-white/10 pt-6">
                        <x-status-tracker :report="$report" />
                    </div>

                    @if ($report->public_reply)
                        <div class="mt-6 overflow-hidden rounded-xl border border-sky-400/20 bg-sky-400/10" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 p-4 text-left">
                                <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-xs font-medium uppercase tracking-wide text-sky-300">Balasan Petugas</span>
                                    <span class="text-xs text-slate-400">{{ $report->public_reply_at->translatedFormat('d M Y H:i') }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-sky-300 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="px-4 pb-4">
                                <p class="whitespace-pre-line text-sm text-slate-100">{{ $report->public_reply }}</p>
                                @php
                                    // Prefill nomor HP cuma dari apa yang pelapor SENDIRI ketik di
                                    // kotak pencarian — bukan men-decrypt data siapa pun.
                                    $queryLooksLikePhone = (bool) preg_match('/^\+?\d[\d\s-]{7,}$/', trim($query));
                                @endphp
                                <button type="button"
                                        x-data="{ ticketNo: @js($report->ticket_no), phone: @js($queryLooksLikePhone ? trim($query) : null) }"
                                        @click="window.dispatchEvent(new CustomEvent('chat-widget:open', { detail: { ticketNo, phone } }))"
                                        class="mt-3 rounded-md border border-sky-400/30 bg-sky-400/10 px-3 py-1.5 text-xs font-medium text-sky-200 hover:bg-sky-400/20">
                                    Kurang puas? Chat dengan Petugas
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-center text-sm text-slate-300 backdrop-blur-lg">
                    Tidak ditemukan laporan dengan Nomor Tiket / Nomor HP tersebut.
                </div>
            @endforelse
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('public.status.check') }}" class="text-sm text-sky-300 hover:underline">&larr; Cek nomor lain</a>
        </div>
    </section>
</x-layouts.public>
