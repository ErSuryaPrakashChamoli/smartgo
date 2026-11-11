@props([
    'label' => null,
    'compact' => false,
    'top' => false,
])

{{-- What a photo is, over its corner (OD-176): a stock photo on a location or workspace page says it is not the location. The parent must be positioned. --}}
@if (filled($label))
    <span {{ $attributes->class([
        'pointer-events-none absolute inline-flex items-center gap-1.5 rounded-full bg-forest/85 font-medium text-paper ring-1 ring-white/15 backdrop-blur',
        'right-3 max-w-[calc(100%-1.5rem)] px-3 py-1.5 text-xs' => ! $compact,
        'right-2 max-w-[calc(100%-1rem)] px-2 py-1 text-[0.6875rem]' => $compact,
        'top-3' => $top,
        'bottom-3' => ! $top && ! $compact,
        'bottom-2' => ! $top && $compact,
    ]) }}>
        <x-heroicon-m-information-circle class="size-3.5 shrink-0 text-leaf-300" aria-hidden="true" />
        {{ $label }}
    </span>
@endif
