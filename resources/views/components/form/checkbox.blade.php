@props([
    'id',
    'label',
    'hint' => null,
])

<div class="flex items-start gap-3">
    <input
        id="{{ $id }}"
        type="checkbox"
        @if ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->class(['mt-0.5 size-5 shrink-0 cursor-pointer rounded border-control accent-leaf-700 disabled:cursor-not-allowed']) }}
    >
    <div class="flex flex-col gap-0.5">
        <label for="{{ $id }}" class="cursor-pointer text-sm font-medium text-ink">{{ $label }}</label>
        @if ($hint)
            <p id="{{ $id }}-hint" class="text-sm text-muted">{{ $hint }}</p>
        @endif
    </div>
</div>
