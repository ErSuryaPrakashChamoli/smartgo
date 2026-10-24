@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'loading' => false,
    'disabled' => false,
])

@php
    $isInactive = $disabled || $loading;

    $classes = [
        'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg font-semibold whitespace-nowrap transition-colors duration-150',
        'h-9 px-3.5 text-sm' => $size === 'sm',
        'h-11 px-5 text-[0.9375rem]' => $size === 'md',
        'h-13 px-6 text-base' => $size === 'lg',
        'cursor-not-allowed' => $isInactive,
        'bg-leaf-700 text-white hover:bg-leaf-800 active:bg-leaf-900' => $variant === 'primary' && ! $isInactive,
        'bg-surface text-forest ring-1 ring-control ring-inset hover:bg-mist' => $variant === 'secondary' && ! $isInactive,
        'text-leaf-700 hover:bg-mist hover:text-leaf-800' => $variant === 'ghost' && ! $isInactive,
        'bg-paper text-forest hover:bg-white' => $variant === 'inverse' && ! $isInactive,
        'text-paper ring-1 ring-paper/60 ring-inset hover:bg-white/10' => $variant === 'inverse-outline' && ! $isInactive,
        'bg-line text-muted' => $isInactive && in_array($variant, ['primary', 'inverse'], true),
        'text-muted ring-1 ring-line ring-inset' => $isInactive && in_array($variant, ['secondary', 'inverse-outline'], true),
        'text-muted' => $isInactive && $variant === 'ghost',
    ];
@endphp

@if ($href && ! $isInactive)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
        @if ($icon)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="size-4" aria-hidden="true" />
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($isInactive)
        @if ($loading) aria-busy="true" @endif
        {{ $attributes->class($classes) }}
    >
        @if ($loading)
            <svg class="size-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
        @endif
        {{ $slot }}
        @if ($icon && ! $loading)
            <x-dynamic-component :component="'heroicon-m-'.$icon" class="size-4" aria-hidden="true" />
        @endif
    </button>
@endif
