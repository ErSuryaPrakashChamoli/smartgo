@props([
    'eyebrow' => null,
    'title',
    'level' => 'h2',
    'align' => 'start',
    'tone' => 'light',
])

<div {{ $attributes->class(['flex max-w-3xl flex-col gap-3', 'mx-auto items-center text-center' => $align === 'center']) }}>
    @if ($eyebrow)
        <p @class(['text-eyebrow font-semibold uppercase', 'text-leaf-700' => $tone === 'light', 'text-leaf-300' => $tone === 'dark'])>{{ $eyebrow }}</p>
    @endif
    <{{ $level }} @class(['font-display text-display-lg font-semibold text-balance', 'text-forest' => $tone === 'light', 'text-paper' => $tone === 'dark'])>{{ $title }}</{{ $level }}>
    @if ($slot->isNotEmpty())
        <div @class(['text-lead text-pretty', 'text-muted' => $tone === 'light', 'text-paper/85' => $tone === 'dark'])>{{ $slot }}</div>
    @endif
</div>
