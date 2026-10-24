@props([
    'name',
    'icon',
    'href' => '#',
])

<article {{ $attributes->class(['group relative grid grid-cols-[auto_1fr] items-start gap-x-4 gap-y-1 rounded-2xl bg-surface p-4 ring-1 ring-line transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card hover:ring-leaf-300 motion-reduce:hover:translate-y-0 sm:flex sm:flex-col sm:gap-4 sm:p-6']) }}>
    <span class="row-span-2 flex size-11 items-center justify-center rounded-xl bg-mist text-leaf-700 transition-colors group-hover:bg-leaf-700 group-hover:text-white sm:size-12">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" aria-hidden="true" />
    </span>
    <h3 class="font-display text-subheading font-semibold text-forest">
        <a href="{{ $href }}" class="rounded-sm after:absolute after:inset-0">{{ $name }}</a>
    </h3>
    <p class="text-sm text-muted">{{ $slot }}</p>
    <span class="mt-auto hidden items-center gap-1 text-sm font-semibold text-leaf-700 sm:inline-flex">
        Explore
        <x-heroicon-m-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
    </span>
</article>
