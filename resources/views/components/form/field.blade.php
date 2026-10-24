@props([
    'label',
    'for',
    'hint' => null,
    'error' => null,
    'required' => false,
])

{{-- Wraps one control with its label, hint and error; the control references them through aria-describedby. --}}
<div {{ $attributes->class(['flex flex-col gap-1.5']) }}>
    <label for="{{ $for }}" class="text-sm font-semibold text-forest">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint && ! $error)
        <p id="{{ $for }}-hint" class="text-sm text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $for }}-error" class="flex items-center gap-1.5 text-sm font-medium text-danger">
            <x-heroicon-m-exclamation-circle class="size-4 shrink-0" aria-hidden="true" />
            {{ $error }}
        </p>
    @endif
</div>
