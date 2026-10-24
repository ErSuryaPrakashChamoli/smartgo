<x-layouts::site
    title="Locations (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page flex flex-col gap-12 py-10 lg:gap-16 lg:py-16">
        <div class="flex flex-col gap-4">
            <x-site.breadcrumb :items="[['label' => 'Home', 'href' => route('design.home')], ['label' => 'Locations']]" />
            <x-ui.section-heading level="h1" eyebrow="Delhi NCR" title="Find a SmartGro workspace">
                Choose a city to see its properties, the workspace types they offer and how to visit.
            </x-ui.section-heading>
        </div>

        <section aria-labelledby="cities" class="flex flex-col gap-6">
            <h2 id="cities" class="sr-only">Cities</h2>
            <ul class="grid gap-5 lg:grid-cols-2">
                @foreach (collect($cities)->where('preview', 'operating') as $city)
                    <li class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex flex-col gap-1">
                                <p class="text-eyebrow font-semibold text-leaf-700 uppercase">City</p>
                                <h3 class="font-display text-display-lg font-semibold text-forest">
                                    <a href="{{ route('design.city', $city['slug']) }}" class="rounded-sm after:absolute after:inset-0">{{ $city['name'] }}</a>
                                </h3>
                            </div>
                            <x-ui.badge tone="brand" icon="building-office-2">{{ collect($properties)->where('citySlug', $city['slug'])->count() }} properties</x-ui.badge>
                        </div>
                        <ul class="flex flex-wrap gap-2" aria-label="Areas in {{ $city['name'] }}">
                            @foreach (collect($properties)->where('citySlug', $city['slug'])->countBy('area') as $area => $count)
                                <li class="rounded-md bg-paper px-2.5 py-1 text-sm text-ink ring-1 ring-line">{{ $area }} <span class="text-muted">· {{ $count }}</span></li>
                            @endforeach
                        </ul>
                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-leaf-700">
                            See properties in {{ $city['name'] }}
                            <x-heroicon-m-arrow-right class="size-4" aria-hidden="true" />
                        </span>
                    </li>
                @endforeach
                <li>
                    <x-ui.placeholder icon="map" label="Map of SmartGro properties (added with the map provider in Phase 6)" class="h-full min-h-56" />
                </li>
            </ul>
            <x-site.design-note title="Which cities appear here">
                Only cities an administrator has made visible are listed. Gurugram, Delhi, Faridabad and Ghaziabad are hidden by default (OD-18). A visible city without a property opens a "Coming soon: register your interest" page instead (OD-22): see the <a href="{{ route('design.city', 'gurugram') }}" class="font-semibold underline underline-offset-2">Gurugram example</a>. The old name "Gurgaon" redirects permanently to Gurugram.
            </x-site.design-note>
        </section>

        <section aria-labelledby="all-properties" class="flex flex-col gap-6">
            <h2 id="all-properties" class="font-display text-heading font-semibold text-forest">All properties</h2>
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($properties as $property)
                    <li>
                        <x-card.property
                            :name="$property['name']"
                            :area="$property['area']"
                            :city="$property['city']"
                            :href="route('design.property', [$property['citySlug'], $property['slug']])"
                            class="h-full"
                        />
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts::site>
