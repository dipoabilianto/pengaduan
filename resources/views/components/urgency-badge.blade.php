@props(['flag'])

@php
    // Colorblind-safe by design: shape (icon) is the primary differentiator, the
    // status hue is a secondary reinforcement, and the label text is always dark/neutral
    // — never colored — so meaning never depends on being able to tell hues apart.
    $variants = [
        'red_code' => [
            'color' => 'text-status-critical',
            'bg' => 'bg-status-critical/10',
            'weight' => 'font-semibold',
            // exclamation-circle (solid dot) — heaviest mark for the most severe flag
            'path' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        ],
        'tinggi' => [
            'color' => 'text-status-serious',
            'bg' => 'bg-status-serious/10',
            'weight' => 'font-medium',
            // exclamation-triangle
            'path' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        ],
        'sedang' => [
            'color' => 'text-status-warning',
            'bg' => 'bg-status-warning/10',
            'weight' => 'font-medium',
            // minus-circle
            'path' => 'M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'rendah' => [
            'color' => 'text-status-good',
            'bg' => 'bg-status-good/10',
            'weight' => 'font-medium',
            // check-circle
            'path' => 'M9 12.75l2.25 2.25 4.5-4.5m6 2.25a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'tidak_valid' => [
            'color' => 'text-gray-500',
            'bg' => 'bg-gray-100',
            'weight' => 'font-medium',
            // x-circle
            'path' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    ];

    // "tinggi" gets its own shape (triangle) distinct from "red_code" (circle) even
    // though both share the exclamation glyph — the outline shape is what differs.
    $variants['tinggi']['path'] = 'M12 9v2.25m0 3.75h.008v.008H12V15zM9.401 3.003c1.155-2 4.043-2 5.198 0l7.518 13.003c1.155 2-.29 4.5-2.599 4.5H4.482c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003z';

    $variant = $variants[$flag] ?? $variants['tidak_valid'];
@endphp

<span class="inline-flex items-center gap-1 rounded-full {{ $variant['bg'] }} px-2.5 py-0.5 text-xs {{ $variant['weight'] }} text-gray-800">
    <svg class="h-3.5 w-3.5 shrink-0 {{ $variant['color'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $variant['path'] }}" />
    </svg>
    {{ \App\Models\Report::URGENCY_LABELS[$flag] ?? $flag }}
</span>
