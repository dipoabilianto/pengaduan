@props(['report'])

@php
    $order = ['diterima', 'diproses', 'diteruskan', 'selesai'];
    $labels = [
        'diterima' => 'Diterima',
        'diproses' => 'Diproses',
        'diteruskan' => 'Diteruskan',
        'selesai' => 'Selesai',
    ];
    $group = $report->publicStatusGroup();
    $isRejected = $group === 'ditolak';

    if ($isRejected) {
        // A report can be rejected straight from "Baru Masuk" (e.g. AI/Admin flags it
        // "Tidak Valid" immediately) without ever reaching "Diproses"/"Diteruskan" — only
        // show the stages it genuinely passed through, not the full sequence by default.
        $reached = $report->reachedPublicGroups();

        $steps = collect(['diterima', 'diproses', 'diteruskan'])
            ->filter(fn ($key) => in_array($key, $reached, true))
            ->map(fn ($key) => ['key' => $key, 'label' => $labels[$key], 'state' => 'done'])
            ->push(['key' => 'ditolak', 'label' => 'Ditolak', 'state' => 'rejected'])
            ->all();
    } else {
        $currentIndex = array_search($group, $order);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $steps = collect($order)->map(fn ($key, $i) => [
            'key' => $key,
            'label' => $labels[$key],
            // "Selesai" is the last step — once reached it's genuinely finished, never "in progress"
            // (unlike every other step, which is "current" the moment the report reaches it).
            'state' => $i < $currentIndex ? 'done' : ($i === $currentIndex ? ($key === 'selesai' ? 'done' : 'current') : 'pending'),
        ])->all();
    }

    $icons = [
        'diterima' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'diproses' => 'M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z',
        'diteruskan' => 'M14 5l7 7m0 0l-7 7m7-7H3',
        'selesai' => 'M5 13l4 4L19 7',
        'ditolak' => 'M6 18L18 6M6 6l12 12',
    ];
@endphp

<div class="status-tracker" role="list" aria-label="Progres status laporan">
    {{-- Mobile: vertical timeline --}}
    <ol class="flex flex-col gap-0 sm:hidden">
        @foreach ($steps as $i => $step)
            <li role="listitem" class="tracker-node relative flex gap-4 pb-8 last:pb-0" style="animation-delay: {{ $i * 120 }}ms">
                @if (!$loop->last)
                    <span class="absolute left-[15px] top-8 h-full w-0.5 -translate-x-1/2 bg-white/10">
                        @if ($step['state'] === 'done' || ($step['state'] === 'rejected'))
                            <span class="tracker-fill-v block w-full bg-gradient-to-b {{ $step['state'] === 'rejected' ? 'from-status-critical to-status-critical' : 'from-status-good to-sky-400' }}" style="animation-delay: {{ $i * 120 + 150 }}ms"></span>
                        @endif
                    </span>
                @endif

                <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 {{ match($step['state']) {
                    'done' => 'border-status-good bg-status-good/20 text-status-good',
                    'current' => 'border-sky-400 bg-sky-400/20 text-sky-300',
                    'rejected' => 'border-status-critical bg-status-critical/20 text-status-critical',
                    default => 'border-white/20 bg-white/5 text-slate-500',
                } }}">
                    @if ($step['state'] === 'current')
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400/40"></span>
                    @endif
                    <svg class="relative h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$step['key']] }}" />
                    </svg>
                </span>

                <div class="pt-1">
                    <p class="text-sm font-medium {{ $step['state'] === 'pending' ? 'text-slate-500' : 'text-slate-100' }}">{{ $step['label'] }}</p>
                    @if ($step['state'] === 'current')
                        <p class="text-xs text-sky-300">Sedang berjalan</p>
                    @elseif ($step['state'] === 'rejected')
                        <p class="text-xs text-rose-300">Laporan tidak dilanjutkan</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    {{-- Desktop: horizontal stepper. Each node's <li> is locked to the CIRCLE's own width
         (w-8/md:w-10, not the label's) and the label is taken out of flow (absolute,
         centered under the circle) so a longer label never widens the node — otherwise
         the circle gets centered inside a wider box and the connector (a separate flex-1
         sibling, attaching to the node's outer edge) ends up an uneven distance from the
         circle on each side, worse the more the two neighboring labels' widths differ. --}}
    {{-- pb-16 reserves room for the labels below, which are positioned absolute (out of
         flow) so their width never affects the node's — otherwise this <ol> would collapse
         to just the circle row's height and the labels would overlap whatever comes next. --}}
    <ol class="hidden pb-16 sm:flex sm:items-start">
        @foreach ($steps as $i => $step)
            <li role="listitem" class="tracker-node relative flex w-8 shrink-0 flex-col items-center md:w-10" style="animation-delay: {{ $i * 120 }}ms">
                <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 md:h-10 md:w-10 {{ match($step['state']) {
                    'done' => 'border-status-good bg-status-good/20 text-status-good',
                    'current' => 'border-sky-400 bg-sky-400/20 text-sky-300',
                    'rejected' => 'border-status-critical bg-status-critical/20 text-status-critical',
                    default => 'border-white/20 bg-white/5 text-slate-500',
                } }}">
                    @if ($step['state'] === 'current')
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400/40"></span>
                    @endif
                    <svg class="relative h-4 w-4 md:h-5 md:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$step['key']] }}" />
                    </svg>
                </span>

                <div class="absolute left-1/2 top-full mt-2 w-24 -translate-x-1/2 px-1 text-center md:mt-3">
                    <p class="text-xs font-medium leading-tight md:text-sm {{ $step['state'] === 'pending' ? 'text-slate-500' : 'text-slate-100' }}">{{ $step['label'] }}</p>
                    @if ($step['state'] === 'current')
                        <p class="text-[11px] text-sky-300 md:text-xs">Sedang berjalan</p>
                    @elseif ($step['state'] === 'rejected')
                        <p class="text-[11px] text-rose-300 md:text-xs">Tidak dilanjutkan</p>
                    @endif
                </div>
            </li>

            @if (!$loop->last)
                <li aria-hidden="true" class="mt-[15px] h-0.5 flex-1 overflow-hidden rounded-full bg-white/10 md:mt-[19px]">
                    @if ($step['state'] === 'done')
                        <span class="tracker-fill-h block h-full bg-gradient-to-r from-status-good to-sky-400" style="animation-delay: {{ $i * 120 + 150 }}ms"></span>
                    @endif
                </li>
            @endif
        @endforeach
    </ol>
</div>
