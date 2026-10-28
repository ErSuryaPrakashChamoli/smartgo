{{-- Every city SmartGro shows, with its open properties; cities shown before a property opens are marked coming soon (OD-22). --}}
<x-layouts::site title="Locations" description="SmartGro workspace locations." :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-12 py-12 sm:py-16">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex max-w-3xl flex-col gap-3">
                <p class="text-eyebrow font-semibold text-leaf-700 uppercase">Locations</p>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">SmartGro locations</h1>
            </div>
            @if ($canCompare)
                <x-ui.button variant="secondary" :href="route('locations.compare')" icon="table-cells" class="self-start sm:self-auto">Compare locations</x-ui.button>
            @endif
        </div>

        @forelse ($cities as $city)
            <section class="flex flex-col gap-5" aria-labelledby="city-{{ $city->slug }}">
                <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-line pb-3">
                    <h2 id="city-{{ $city->slug }}" class="font-display text-heading font-semibold text-forest">
                        <a href="/locations/{{ $city->slug }}" class="hover:text-leaf-700">{{ $city->name }}</a>
                    </h2>
                    <p class="text-sm text-muted">
                        {{ $city->open_properties_count > 0 ? $city->open_properties_count.' '.\Illuminate\Support\Str::plural('location', $city->open_properties_count) : 'Coming soon' }}
                    </p>
                </div>
                @if ($city->open_properties_count > 0)
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($propertiesByCity->get($city->id, collect()) as $property)
                            <x-site.location-card :property="$property" />
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            <p class="text-muted">No locations are published yet.</p>
        @endforelse
    </div>
</x-layouts::site>
