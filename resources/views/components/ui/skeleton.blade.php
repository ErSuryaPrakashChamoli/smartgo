@props(['label' => 'Loading'])

{{-- Loading placeholder; pulses only when the visitor has not asked for reduced motion. --}}
<div role="status" aria-live="polite" {{ $attributes->class(['flex flex-col gap-3']) }}>
    <span class="sr-only">{{ $label }}</span>
    <div class="aspect-[4/3] w-full rounded-xl bg-line motion-safe:animate-pulse"></div>
    <div class="h-5 w-2/3 rounded bg-line motion-safe:animate-pulse"></div>
    <div class="h-4 w-1/2 rounded bg-line motion-safe:animate-pulse"></div>
</div>
