{{-- One city: its properties on the website by sector, each sector linking to its own page, narrowed to one workspace type by plain links that work without JavaScript (FR-DISC-2). --}}
@php
    $shownCount = $sectors->sum(fn (array $group): int => $group['properties']->count());
@endphp
<x-layouts::site :title="'Workspaces in '.$city->name" :description="'SmartGro workspace locations in '.$city->name.'.'" :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <nav aria-label="Breadcrumb" class="text-sm text-muted">
            <a href="/locations" class="hover:text-leaf-700">Locations</a> <span aria-hidden="true">/</span> <span aria-current="page">{{ $city->name }}</span>
        </nav>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <h1 class="max-w-3xl font-display text-display-xl font-semibold text-balance text-forest">Workspaces in {{ $city->name }}</h1>
            @if ($canCompare)
                <x-ui.button variant="secondary" :href="route('locations.compare')" icon="table-cells" class="self-start sm:self-auto">Compare locations</x-ui.button>
            @endif
        </div>

        @if ($workspaceTypes->count() > 1)
            <x-site.workspace-filter :types="$workspaceTypes" :current="$workspace" :path="'/locations/'.$city->slug" />
        @endif

        @if ($workspace !== null)
            <p class="text-muted" role="status">
                {{ $shownCount }} {{ str('location')->plural($shownCount) }} in {{ $city->name }} {{ $shownCount === 1 ? 'offers' : 'offer' }} {{ \Illuminate\Support\Str::lower($workspace->name) }}.
                <a href="/workspaces/{{ $workspace->slug }}/{{ $city->slug }}" class="font-semibold text-leaf-700 hover:underline">About {{ \Illuminate\Support\Str::lower($workspace->name) }} in {{ $city->name }}</a>
            </p>
        @endif

        @if ($sectors->isEmpty())
            <div class="flex max-w-2xl flex-col gap-2 rounded-2xl bg-mist p-6">
                <p class="font-display text-subheading font-semibold text-forest">Coming soon</p>
                <p class="text-muted">SmartGro does not have an open location in {{ $city->name }} yet.</p>
            </div>
        @else
            @foreach ($sectors as $group)
                <section class="flex flex-col gap-4">
                    <h2 class="text-sm font-semibold tracking-wide text-muted uppercase">
                        @if ($group['sector'])
                            <a href="{{ $group['sector']->path() }}" class="hover:text-leaf-700">{{ $group['sector']->name }}</a>
                        @else
                            {{ $city->name }}
                        @endif
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group['properties'] as $property)
                            <x-site.location-card :property="$property" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts::site>
