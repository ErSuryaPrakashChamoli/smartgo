@props([
    'icon' => 'map',
    'label',
])

{{-- Labelled stand-in for content SmartGro has not supplied yet (maps, floor plans, 3D scenes, documents). --}}
<div
    role="img"
    aria-label="Placeholder: {{ $label }}"
    {{ $attributes->class(['flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-control/60 bg-paper p-6 text-center']) }}
>
    <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-8 text-leaf-700" aria-hidden="true" />
    <span class="max-w-xs text-sm font-medium text-muted">{{ $label }}</span>
</div>
