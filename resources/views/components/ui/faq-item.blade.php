@props(['question'])

{{-- Native disclosure: keyboard and screen-reader support without JavaScript. --}}
<details {{ $attributes->class(['group border-b border-line py-1']) }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-md py-4 text-left font-semibold text-forest [&::-webkit-details-marker]:hidden">
        {{ $question }}
        <x-heroicon-m-chevron-down class="size-5 shrink-0 text-leaf-700 transition-transform duration-200 group-open:rotate-180" aria-hidden="true" />
    </summary>
    <div class="pb-5 text-muted">{{ $slot }}</div>
</details>
