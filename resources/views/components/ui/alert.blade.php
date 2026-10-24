@props([
    'type' => 'info',
    'title' => null,
])

@php
    $styles = [
        'info' => ['icon' => 'information-circle', 'classes' => 'bg-info-soft text-info'],
        'success' => ['icon' => 'check-circle', 'classes' => 'bg-available-soft text-available'],
        'warning' => ['icon' => 'exclamation-triangle', 'classes' => 'bg-limited-soft text-limited'],
        'error' => ['icon' => 'x-circle', 'classes' => 'bg-unavailable-soft text-unavailable'],
    ];

    $style = $styles[$type] ?? $styles['info'];
@endphp

<div
    role="{{ $type === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->class(['flex gap-3 rounded-xl p-4 text-sm', $style['classes']]) }}
>
    <x-dynamic-component :component="'heroicon-m-'.$style['icon']" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
    <div class="flex flex-col gap-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="text-ink">{{ $slot }}</div>
    </div>
</div>
