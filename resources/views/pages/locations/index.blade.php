{{-- Every city SmartGro shows, with its open properties; cities shown before a property opens are marked coming soon (OD-22). Narrowed to one workspace type by plain links (FR-DISC-2). --}}
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

        @if ($workspaceTypes->count() > 1)
            <div class="-mt-4 flex flex-col gap-4">
                <x-site.workspace-filter :types="$workspaceTypes" :current="$workspace" path="/locations" />
                @if ($workspace !== null)
                    <p class="text-muted" role="status">
                        {{ $shownCount }} SmartGro {{ str('location')->plural($shownCount) }} {{ $shownCount === 1 ? 'offers' : 'offer' }} {{ \Illuminate\Support\Str::lower($workspace->name) }}.
                        <a href="/workspaces/{{ $workspace->slug }}" class="font-semibold text-leaf-700 hover:underline">About {{ \Illuminate\Support\Str::lower($workspace->name) }}</a>
                    </p>
                @endif
            </div>
        @endif

        @forelse ($cities as $city)
            @php($cityCount = $workspace === null ? $city->open_properties_count : $propertiesByCity->get($city->id, collect())->count())
            <section class="flex flex-col gap-5" aria-labelledby="city-{{ $city->slug }}">
                <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-line pb-3">
                    <h2 id="city-{{ $city->slug }}" class="font-display text-heading font-semibold text-forest">
                        <a href="/locations/{{ $city->slug }}" class="hover:text-leaf-700">{{ $city->name }}</a>
                    </h2>
                    <p class="text-sm text-muted">
                        {{ $cityCount > 0 ? $cityCount.' '.\Illuminate\Support\Str::plural('location', $cityCount) : 'Coming soon' }}
                    </p>
                </div>
                @if ($cityCount > 0)
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
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
