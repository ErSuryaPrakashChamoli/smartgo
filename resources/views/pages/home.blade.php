{{-- The public home page. Every fact comes from records; nothing about prices or availability is shown. --}}
<x-layouts::site
    :title="null"
    description="SmartGro flexible workspaces: private offices, desks, meeting rooms, virtual offices and managed offices{{ $cityNames === [] ? '' : ' in '.implode(', ', $cityNames) }}."
    :canonical="$canonical"
    :navigation="$navigation"
>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-forest text-paper">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(60rem_30rem_at_85%_-10%,rgba(132,189,83,0.22),transparent_60%)]" aria-hidden="true"></div>
        <div class="container-page grid items-center gap-12 py-16 sm:py-20 lg:grid-cols-12 lg:py-24">
            <div class="flex flex-col gap-6 lg:col-span-7">
                <p class="text-eyebrow font-semibold tracking-wide text-leaf-300 uppercase">Work smart. Grow together.</p>
                <h1 class="font-display text-display-2xl font-semibold text-balance">
                    Flexible workspaces{{ $cityNames === [] ? '' : ' in '.implode(', ', $cityNames) }}
                </h1>
                <p class="max-w-2xl text-lead text-pretty text-paper/85">
                    Private offices, dedicated and hot desks, meeting rooms, virtual offices and managed offices for freelancers, startups, growing companies and enterprises.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" variant="inverse" href="#workspaces" icon="squares-2x2">Explore workspaces</x-ui.button>
                    <x-ui.button size="lg" variant="inverse-outline" href="#contact">Talk to SmartGro</x-ui.button>
                </div>
            </div>

            @if ($properties->isNotEmpty())
                <div class="lg:col-span-5">
                    <div class="flex flex-col gap-4 rounded-3xl bg-paper p-6 text-ink shadow-raised sm:p-7">
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-display text-subheading font-semibold text-forest">Our locations</p>
                            <span class="rounded-full bg-mist px-3 py-1 text-sm font-semibold text-forest">{{ $properties->count() }}</span>
                        </div>
                        <ul class="flex flex-col divide-y divide-line">
                            @foreach ($properties as $property)
                                <li>
                                    <a href="{{ $property->path() }}" class="group flex items-center justify-between gap-4 py-3">
                                        <span class="flex flex-col">
                                            <span class="font-semibold text-forest group-hover:text-leaf-700">SmartGro {{ $property->name }}</span>
                                            <span class="text-sm text-muted">{{ collect([$property->microMarket?->name, $property->city->name])->filter()->join(', ') }}</span>
                                        </span>
                                        <x-heroicon-m-arrow-right class="size-5 shrink-0 text-leaf-700 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <x-ui.button variant="secondary" href="/locations" icon="map-pin">All locations</x-ui.button>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Workspace categories --}}
    @if ($categories !== [])
        <section id="workspaces" class="scroll-mt-24 py-16 sm:py-20">
            <div class="container-page flex flex-col gap-10">
                <x-ui.section-heading eyebrow="Workspaces" title="Space for every way of working">
                    From a single desk to a whole office, choose the kind of workspace that fits your team.
                </x-ui.section-heading>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($categories as $category)
                        <li class="relative flex flex-col gap-3 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line transition-shadow duration-200 hover:shadow-raised">
                            @if ($category['icon'])
                                <span class="flex size-11 items-center justify-center rounded-xl bg-mist text-leaf-700" aria-hidden="true">
                                    <x-dynamic-component :component="'heroicon-o-'.$category['icon']" class="size-6" />
                                </span>
                            @endif
                            <h3 class="font-display text-subheading font-semibold text-forest">
                                @if ($category['path'])
                                    <a href="{{ $category['path'] }}" class="rounded-sm after:absolute after:inset-0 hover:text-leaf-700">{{ $category['name'] }}</a>
                                @elseif ($category['group'])
                                    <a href="#{{ \App\Services\Website\WorkspaceCatalogue::GROUPS[$category['group']]['anchor'] }}" class="rounded-sm after:absolute after:inset-0 hover:text-leaf-700">{{ $category['name'] }}</a>
                                @else
                                    {{ $category['name'] }}
                                @endif
                            </h3>
                            @if ($category['summary'])
                                <p class="text-muted">{{ $category['summary'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- One section per group of workspace types --}}
    @foreach ($groups as $group)
        <section id="{{ $group['anchor'] }}" @class(['scroll-mt-24 py-14 sm:py-16', 'bg-mist/60' => $loop->odd])>
            <div class="container-page grid gap-8 lg:grid-cols-12 lg:items-center">
                <div class="flex flex-col gap-4 lg:col-span-6">
                    <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $group['title'] }}</h2>
                    <p class="max-w-xl text-lead text-pretty text-muted">{{ $group['description'] }}</p>
                    <div><x-ui.button variant="secondary" :href="route('enquiries.create', count($group['categories']) === 1 ? ['workspace' => $group['categories'][0]['slug']] : [])" icon="chat-bubble-left-right" aria-label="Ask SmartGro about {{ \Illuminate\Support\Str::lower($group['title']) }}">Ask SmartGro</x-ui.button></div>
                </div>
                <div class="flex flex-col gap-4 lg:col-span-6">
                    @foreach ($group['categories'] as $category)
                        <div class="flex flex-col gap-3 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                            <p class="font-display text-subheading font-semibold text-forest">
                                @if ($category['path'])
                                    <a href="{{ $category['path'] }}" class="hover:text-leaf-700">{{ $category['name'] }}</a>
                                @else
                                    {{ $category['name'] }}
                                @endif
                            </p>
                            @if ($category['offeredAt'] !== [])
                                <p class="text-sm text-muted">Available at</p>
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($category['offeredAt'] as $location)
                                        <li><a href="{{ $location['path'] }}" class="inline-flex rounded-md bg-paper px-2.5 py-1 text-sm font-medium text-forest ring-1 ring-line hover:bg-mist">SmartGro {{ $location['name'] }}</a></li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-muted">Ask us which locations offer this.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    {{-- Locations --}}
    @if ($properties->isNotEmpty())
        <section id="locations" class="scroll-mt-24 py-16 sm:py-20">
            <div class="container-page flex flex-col gap-10">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <x-ui.section-heading eyebrow="Locations" title="Find a SmartGro near you">
                        {{ $properties->count() }} {{ \Illuminate\Support\Str::plural('location', $properties->count()) }}{{ $cityNames === [] ? '' : ' in '.implode(', ', $cityNames) }}.
                    </x-ui.section-heading>
                    <x-ui.button variant="secondary" href="/locations" icon="map-pin">All locations</x-ui.button>
                </div>
                <div class="flex flex-col gap-10">
                    @foreach ($propertiesBySector as $sector => $sectorProperties)
                        <div class="flex flex-col gap-4">
                            <h3 class="text-sm font-semibold tracking-wide text-muted uppercase">{{ $sector }}</h3>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($sectorProperties as $property)
                                    <x-site.location-card :property="$property" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-site.contact-section :contact="$contact" />
</x-layouts::site>
