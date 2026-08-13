@props(['state', 'reason', 'showReason' => true])

@php
    // Colorblind-safe by design: each state gets its own icon shape (check / pause /
    // warning-triangle), not just a color swap — see resources/views/components/urgency-badge.blade.php
    // for the same principle applied to urgency flags.
    $labels = ['active' => 'Aktif', 'off' => 'Tidak Aktif', 'error' => 'Bermasalah'];
    $icons = [
        'active' => 'M9 12.75l2.25 2.25 4.5-4.5m6 2.25a9 9 0 11-18 0 9 9 0 0118 0z',
        'off' => 'M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z',
        'error' => 'M12 9v2.25m0 3.75h.008v.008H12V15zM9.401 3.003c1.155-2 4.043-2 5.198 0l7.518 13.003c1.155 2-.29 4.5-2.599 4.5H4.482c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003z',
    ];
@endphp

<div
    x-data="{
        state: @js($state),
        reason: @js($reason),
        label: @js($labels),
        icon: @js($icons),
        poll() {
            axios.get('{{ route('admin.ai-health') }}')
                .then((res) => { this.state = res.data.state; this.reason = res.data.reason; })
                .catch(() => {});
        },
        init() {
            setInterval(() => this.poll(), 20000);
        },
    }"
    {{ $attributes->merge(['class' => 'inline-flex flex-col']) }}
>
    <span
        title="{{ $reason }}"
        :title="reason"
        :class="{
            'bg-status-good/10 text-status-good': state === 'active',
            'bg-gray-100 text-gray-600': state === 'off',
            'bg-status-critical/10 text-status-critical': state === 'error',
        }"
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
    >
        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" :d="icon[state]" d="{{ $icons[$state] ?? $icons['off'] }}" />
        </svg>
        <span x-text="'AI Otomatis ' + label[state]">AI Otomatis {{ $labels[$state] ?? $state }}</span>
    </span>

    @if ($showReason)
        <p class="mt-1 text-xs text-gray-400" x-text="reason">{{ $reason }}</p>
    @endif
</div>
