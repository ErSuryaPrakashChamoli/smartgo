@props([
    'properties',
    'photos' => [],
    'cityNames' => [],
])

{{-- The locations on the website, each with its published main photo, or branded artwork until one is published. --}}
<section id="locations" class="scroll-mt-24 py-14 sm:py-20" aria-labelledby="locations-heading">
    <div class="container-page flex flex-col gap-6 sm:gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" data-reveal>
            <div class="flex max-w-2xl flex-col gap-2">
                <h2 id="locations-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Our locations</h2>
                <p class="text-lg text-pretty text-muted">
                    {{ $properties->count() }} SmartGro {{ str('location')->plural($properties->count()) }}{{ $cityNames === [] ? '' : ' in '.implode(', ', $cityNames) }}. Choose one to see its workspaces.
                </p>
            </div>
            <a href="/locations" class="group inline-flex shrink-0 items-center gap-1.5 self-start rounded-full px-1 text-[0.9375rem] font-semibold text-leaf-700 hover:text-leaf-800 sm:self-auto">
                View all locations
                <x-heroicon-m-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </a>
        </div>

        <ul data-reveal-group @class([
            'grid gap-5',
            'sm:grid-cols-2' => $properties->count() > 1,
            'lg:grid-cols-3' => $properties->count() === 3,
            'lg:grid-cols-4' => $properties->count() >= 4,
        ])>
            @foreach ($properties as $property)
                <li>
                    <x-home.location-tile :property="$property" :photo="$photos[$property->getKey()] ?? null" />
                </li>
            @endforeach
        </ul>
    </div>
</section>
