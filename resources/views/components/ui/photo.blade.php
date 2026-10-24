@props([
    'src' => null,
    'alt' => '',
    'label' => 'Photography to be supplied by SmartGro',
])

@php($patternId = 'photo-grid-'.\Illuminate\Support\Str::random(6))

{{-- Shows a real photograph when one exists; otherwise a labelled placeholder, never a stock or AI image presented as real (BR-MED). --}}
@if ($src)
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async" {{ $attributes->class(['object-cover']) }}>
@else
    <div
        role="img"
        aria-label="Photo placeholder: {{ $label }}"
        {{ $attributes->class(['relative isolate flex items-start overflow-hidden bg-mist']) }}
    >
        <svg class="absolute inset-0 -z-10 size-full text-leaf-300/40" aria-hidden="true">
            <defs>
                <pattern id="{{ $patternId }}" width="32" height="32" patternUnits="userSpaceOnUse">
                    <path d="M32 0H0v32" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#{{ $patternId }})" />
        </svg>
        <div class="absolute -right-8 -bottom-10 -z-10 h-3/4 w-2/5 rounded-t-[2.5rem] bg-leaf-300/25" aria-hidden="true"></div>
        <div class="absolute right-16 -bottom-6 -z-10 h-1/2 w-1/4 rounded-t-[2rem] bg-leaf-500/20" aria-hidden="true"></div>
        <span class="m-3 inline-flex items-center gap-1.5 rounded-md bg-surface/90 px-2 py-1 text-xs font-medium text-muted">
            <x-heroicon-m-camera class="size-3.5" aria-hidden="true" />
            {{ $label }}
        </span>
    </div>
@endif
