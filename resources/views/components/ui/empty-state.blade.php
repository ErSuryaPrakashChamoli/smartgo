@props([
    'icon' => 'inbox',
    'title',
])

<div {{ $attributes->class(['flex flex-col items-center gap-3 rounded-2xl border border-dashed border-control/60 bg-surface px-6 py-10 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-mist text-leaf-700">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" aria-hidden="true" />
    </span>
    <p class="font-display text-subheading font-semibold text-forest">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="max-w-md text-sm text-muted">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
