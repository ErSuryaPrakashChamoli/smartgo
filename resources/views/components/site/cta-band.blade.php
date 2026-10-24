@props([
    'eyebrow' => null,
    'title',
])

<section data-surface="dark" {{ $attributes->class(['relative isolate overflow-hidden rounded-3xl bg-forest px-6 py-12 sm:px-12 sm:py-16']) }}>
    <svg class="absolute inset-0 -z-10 size-full text-white/5" aria-hidden="true">
        <defs>
            <pattern id="cta-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                <path d="M48 0H0v48" fill="none" stroke="currentColor" stroke-width="1" />
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#cta-grid)" />
    </svg>
    <div class="absolute -top-24 -right-24 -z-10 size-72 rounded-full bg-leaf-700/40 blur-3xl" aria-hidden="true"></div>
    <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between lg:gap-12">
        <x-ui.section-heading :eyebrow="$eyebrow" :title="$title" tone="dark">{{ $slot }}</x-ui.section-heading>
        @isset($actions)
            <div class="flex flex-wrap gap-3 lg:shrink-0 lg:flex-nowrap">{{ $actions }}</div>
        @endisset
    </div>
</section>
