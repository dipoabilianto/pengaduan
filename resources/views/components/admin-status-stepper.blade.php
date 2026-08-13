@props(['report'])

@php
    $order = ['baru_masuk', 'terverifikasi_admin', 'dalam_penanganan', 'selesai'];
    $isRejected = $report->status === 'ditolak';

    if ($isRejected) {
        // We don't know exactly how far it got before rejection — just show the rejection
        // itself clearly, that's the only thing that matters once a report is Ditolak.
        $steps = [
            ['key' => 'ditolak', 'label' => 'Ditolak/Tidak Valid', 'state' => 'rejected'],
        ];
    } else {
        $currentIndex = array_search($report->status, $order);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $steps = collect($order)->map(fn ($key, $i) => [
            'key' => $key,
            'label' => \App\Models\Report::STATUS_LABELS[$key],
            'state' => $i < $currentIndex ? 'done' : ($i === $currentIndex ? ($key === 'selesai' ? 'done' : 'current') : 'pending'),
        ])->all();
    }
@endphp

<div class="rounded-lg bg-white p-4 shadow" role="list" aria-label="Progres status laporan">
    {{-- Mobile: vertical timeline (no horizontal scrolling needed to see every step) --}}
    <ol class="flex flex-col sm:hidden">
        @foreach ($steps as $i => $step)
            <li role="listitem" class="relative flex gap-3 pb-6 last:pb-0">
                @if (! $loop->last)
                    <span @class([
                        'absolute left-[13px] top-7 h-full w-0.5 -translate-x-1/2',
                        'bg-status-good' => $step['state'] === 'done',
                        'bg-gray-200' => $step['state'] !== 'done',
                    ])></span>
                @endif

                <span @class([
                    'relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                    'border-status-good bg-status-good/10 text-status-good' => $step['state'] === 'done',
                    'border-sky-500 bg-sky-50 text-sky-600' => $step['state'] === 'current',
                    'border-status-critical bg-status-critical/10 text-status-critical' => $step['state'] === 'rejected',
                    'border-gray-200 bg-gray-50 text-gray-400' => $step['state'] === 'pending',
                ])>
                    @if ($step['state'] === 'done')
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @elseif ($step['state'] === 'rejected')
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>

                <p @class([
                    'pt-0.5 text-sm',
                    'font-medium text-gray-900' => in_array($step['state'], ['done', 'current', 'rejected'], true),
                    'text-gray-400' => $step['state'] === 'pending',
                ])>{{ $step['label'] }}</p>
            </li>
        @endforeach
    </ol>

    {{-- Desktop: horizontal stepper --}}
    <ol class="hidden sm:flex sm:items-start">
        @foreach ($steps as $i => $step)
            <li role="listitem" class="flex flex-1 flex-col items-center last:flex-none">
                <div class="flex w-full items-center">
                    <span @class([
                        'relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                        'border-status-good bg-status-good/10 text-status-good' => $step['state'] === 'done',
                        'border-sky-500 bg-sky-50 text-sky-600' => $step['state'] === 'current',
                        'border-status-critical bg-status-critical/10 text-status-critical' => $step['state'] === 'rejected',
                        'border-gray-200 bg-gray-50 text-gray-400' => $step['state'] === 'pending',
                    ])>
                        @if ($step['state'] === 'done')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @elseif ($step['state'] === 'rejected')
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>

                    @if (! $loop->last)
                        <span @class([
                            'mx-1 h-0.5 flex-1',
                            'bg-status-good' => $step['state'] === 'done',
                            'bg-gray-200' => $step['state'] !== 'done',
                        ])></span>
                    @endif
                </div>

                <p @class([
                    'mt-1.5 text-center text-[11px] leading-tight',
                    'font-medium text-gray-900' => in_array($step['state'], ['done', 'current', 'rejected'], true),
                    'text-gray-400' => $step['state'] === 'pending',
                ])>{{ $step['label'] }}</p>
            </li>
        @endforeach
    </ol>
</div>
