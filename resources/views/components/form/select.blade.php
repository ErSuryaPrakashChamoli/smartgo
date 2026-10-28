@props([
    'id',
    'options' => [],
    'placeholder' => null,
    'invalid' => false,
    'describedBy' => null,
    'selected' => null,
])

<div class="relative">
    <select
        id="{{ $id }}"
        @if ($invalid) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'h-12 w-full appearance-none rounded-lg bg-surface pr-10 pl-3.5 text-base text-ink ring-1 ring-inset',
            'focus-visible:ring-2 focus-visible:ring-leaf-700 focus-visible:outline-none',
            'disabled:cursor-not-allowed disabled:bg-paper disabled:text-muted disabled:ring-line',
            'ring-control' => ! $invalid,
            'ring-2 ring-danger' => $invalid,
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected($selected !== null && (string) $value === (string) $selected)>{{ $label }}</option>
        @endforeach
    </select>
    <x-heroicon-m-chevron-up-down class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
</div>
