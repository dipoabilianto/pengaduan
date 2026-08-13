@props(['active' => false])

@php
$classes = ($active ?? false)
    ? 'flex items-center gap-3 rounded-lg bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700'
    : 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
