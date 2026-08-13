<div class="mt-6 space-y-3">
    @forelse ($reports as $report)
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-lg" x-data="{ open: false }">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-mono text-xs text-sky-300">{{ $report['ticket_no'] }}</p>
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium">{{ $report['status'] }}</span>
            </div>
            <p class="mt-2 text-sm text-slate-100">{{ $report['category'] }}</p>
            <p class="mt-1 text-xs text-slate-400">
                {{ $report['type'] === 'whistleblowing' ? 'Whistleblowing' : 'Pengaduan Pelayanan Publik' }}
                @if ($report['where'])
                    &middot; {{ $report['where'] }}
                @endif
                &middot; {{ $report['created_at']->translatedFormat('d M Y') }}
            </p>

            @if ($report['what'])
                <p class="mt-3 whitespace-pre-line text-sm text-slate-200">{{ $report['what'] }}</p>
            @endif

            @if ($report['reply'])
                <div class="mt-3 overflow-hidden rounded-xl border border-sky-400/20 bg-sky-400/10">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 p-3 text-left">
                        <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="text-xs font-medium uppercase tracking-wide text-sky-300">Balasan Petugas</span>
                            @if ($report['reply_at'])
                                <span class="text-xs text-slate-400">{{ $report['reply_at']->translatedFormat('d M Y H:i') }}</span>
                            @endif
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-sky-300 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="px-3 pb-3">
                        <p class="whitespace-pre-line text-sm text-slate-100">{{ $report['reply'] }}</p>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-sm text-slate-300 backdrop-blur-lg">
            Belum ada laporan yang cocok dengan filter ini.
        </div>
    @endforelse
</div>

<div class="mt-6 [&_a]:text-slate-200 [&_span]:text-slate-400">
    {{ $reports->links() }}
</div>
