@props([
    'tone' => 'neutral',
    'icon' => null,
])

@php
    $tones = [
        'neutral' => 'bg-paper text-ink ring-1 ring-line',
        'brand' => 'bg-mist text-forest',
        'success' => 'bg-available-soft text-available',
        'warning' => 'bg-limited-soft text-limited',
        'danger' => 'bg-unavailable-soft text-unavailable',
        'info' => 'bg-info-soft text-info',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', $tones[$tone] ?? $tones['neutral']]) }}>
    @if ($icon)
        <x-dynamic-component :component="'heroicon-m-'.$icon" class="size-3.5 shrink-0" aria-hidden="true" />
    @endif
    {{ $slot }}
</span>
