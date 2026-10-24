@props([
    'name',
    'legend',
    'slots' => [],
    'state' => 'ready',
    'caption' => null,
])

{{--
    Time choice using native radio inputs (no JavaScript). $slots: list of ['value', 'label', 'disabled'].
    States: ready, loading, empty (nothing free that day) and unconfigured (no tour or booking hours set yet).
    Real slots come from availability rules (BR-TR-1, BR-BK-7); the server re-checks under lock on submit.
--}}
<fieldset {{ $attributes->class(['flex flex-col gap-3']) }}>
    <legend class="text-sm font-semibold text-forest">{{ $legend }}</legend>
    @if ($caption)
        <p class="-mt-1 text-sm text-muted">{{ $caption }}</p>
    @endif

    @switch($state)
        @case('loading')
            <div role="status" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <span class="sr-only">Loading times</span>
                @foreach (range(1, 8) as $placeholder)
                    <span class="h-11 rounded-lg bg-line motion-safe:animate-pulse"></span>
                @endforeach
            </div>
            @break
        @case('empty')
            <x-ui.alert type="info" title="No times left on this date">Choose another date, or send an enquiry and we will suggest a time.</x-ui.alert>
            @break
        @case('unconfigured')
            <x-ui.alert type="info" title="Times are not published yet">Tell us a preferred date and part of the day, and a SmartGro team member will confirm a time with you.</x-ui.alert>
            @break
        @default
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach ($slots as $slot)
                    <label @class(['relative', 'cursor-not-allowed' => $slot['disabled'] ?? false])>
                        <input type="radio" name="{{ $name }}" value="{{ $slot['value'] }}" class="peer sr-only" @disabled($slot['disabled'] ?? false) @checked($slot['checked'] ?? false)>
                        <span class="flex h-11 items-center justify-center rounded-lg bg-surface text-sm font-semibold text-forest tabular-nums ring-1 ring-control ring-inset peer-checked:bg-leaf-700 peer-checked:text-white peer-checked:ring-leaf-700 peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-leaf-700 peer-disabled:bg-paper peer-disabled:text-muted peer-disabled:line-through peer-disabled:ring-line">
                            {{ $slot['label'] }}
                        </span>
                    </label>
                @endforeach
            </div>
    @endswitch
</fieldset>
