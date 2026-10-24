@props([
    'id',
    'type' => 'text',
    'invalid' => false,
    'describedBy' => null,
])

<input
    id="{{ $id }}"
    type="{{ $type }}"
    @if ($invalid) aria-invalid="true" @endif
    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->class([
        'h-12 w-full rounded-lg bg-surface px-3.5 text-base text-ink ring-1 ring-inset placeholder:text-muted/80 transition-shadow',
        'focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:outline-none',
        'disabled:cursor-not-allowed disabled:bg-paper disabled:text-muted disabled:ring-line',
        'ring-control' => ! $invalid,
        'ring-2 ring-danger' => $invalid,
    ]) }}
>
