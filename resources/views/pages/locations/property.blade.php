{{-- A property on the website (IA §3.1, the approved centre page design) from published records only, or a staff preview of a profile version. A section appears only when it has content; nothing is a placeholder. --}}
@php
    $photos = $profile['photos'] ?? [];
    $openingHours = $property->openingHours();
    $gettingHere = $profile['gettingHere'] ?? [];
    $hasGettingHere = filled($profile['gettingThere'] ?? null) || $gettingHere !== [] || $mapHref !== null;
    $sections = array_filter([
        'workspaces' => 'Workspaces',
        'photos' => count($photos) > 1 ? 'Photos' : null,
        'getting-here' => $hasGettingHere ? 'Getting here' : null,
        'hours' => $openingHours !== [] ? 'Opening hours' : null,
        'nearby' => count($nearby) > 0 ? 'Nearby' : null,
    ]);
@endphp
<x-layouts::site
    :title="'SmartGro '.$property->name.', '.$property->city->name"
    :meta-title="$profile['seoTitle'] ?? null"
    :description="$profile['seoDescription'] ?? $profile['summary'] ?? 'SmartGro '.$property->name.': '.$property->addressLine().'.'"
    :noindex="$preview !== null"
    :canonical="$canonical"
    :navigation="$navigation"
    :mobile-actions="[['label' => 'Enquire', 'href' => $enquiryHref, 'external' => false]]"
    :mobile-contact="$contact['details']"
>
    @if ($structuredData !== null)
        <x-slot:head>
            <script type="application/ld+json">@json($structuredData)</script>
        </x-slot:head>
    @endif

    @if ($preview !== null)
        <x-slot:banner>
            <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
        </x-slot:banner>
    @endif

    <section class="border-b border-line bg-surface">
        <div @class(['container-page grid gap-8 py-8 lg:gap-12 lg:py-12', 'lg:grid-cols-12' => $photos !== []])>
            @if ($photos !== [])
                <div class="grid grid-cols-4 gap-3 lg:col-span-7">
                    @foreach (array_slice($photos, 0, 4) as $photo)
                        <img src="{{ $photo['src'] }}" srcset="{{ $photo['srcset'] }}" sizes="{{ $loop->first ? '(min-width: 1024px) 700px, 100vw' : '200px' }}" alt="{{ $photo['alt'] }}"
                            @if ($photo['dimensions']) width="{{ $photo['dimensions']['width'] }}" height="{{ $photo['dimensions']['height'] }}" @endif
                            @unless ($loop->first) loading="lazy" @endunless decoding="async"
                            @class(['h-auto w-full bg-mist object-cover', 'col-span-4 aspect-[4/3] rounded-2xl' => $loop->first, 'hidden aspect-square rounded-xl sm:block' => ! $loop->first])>
                    @endforeach
                </div>
            @endif
            <div @class(['flex flex-col gap-5', 'lg:col-span-5' => $photos !== [], 'max-w-3xl' => $photos === []])>
                <x-site.breadcrumb :items="array_values(array_filter([['label' => 'Locations', 'href' => '/locations'], ['label' => $property->city->name, 'href' => '/locations/'.$property->city->slug], $property->microMarket ? ['label' => $property->microMarket->name, 'href' => $property->microMarket->path()] : null, ['label' => 'SmartGro '.$property->name]]))" />
                <div class="flex flex-col gap-2">
                    <p class="flex items-center gap-1.5 text-sm text-muted">
                        <x-heroicon-m-map-pin class="size-4 text-leaf-700" aria-hidden="true" />
                        {{ collect([$property->microMarket?->name, $property->city->name])->filter()->join(', ') }}
                    </p>
                    <h1 class="font-display text-display-xl font-semibold text-balance text-forest">SmartGro {{ $property->name }}</h1>
                    @if (filled($profile['headline'] ?? null))
                        <p class="text-lead text-pretty text-muted">{{ $profile['headline'] }}</p>
                    @endif
                    @if ($property->openingLabel())
                        <x-ui.badge tone="info" icon="clock" class="self-start">{{ $property->openingLabel() }}</x-ui.badge>
                    @endif
                </div>
                <dl class="grid gap-x-6 gap-y-4 rounded-2xl bg-paper p-5 ring-1 ring-line sm:grid-cols-2">
                    <div class="flex flex-col gap-0.5 sm:col-span-2">
                        <dt class="text-sm text-muted">Address</dt>
                        <dd class="font-semibold text-forest">{{ $property->addressLine() }}</dd>
                    </div>
                    @if ($openingHours !== [])
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-sm text-muted">Opening hours</dt>
                            <dd><a href="#hours" class="font-semibold text-leaf-700 hover:underline">See the hours</a></dd>
                        </div>
                    @endif
                    @if ($offerings !== [])
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-sm text-muted">Workspace types</dt>
                            <dd class="font-semibold text-forest">{{ collect($offerings)->map(fn (array $item): string => $item['offering']->product->name)->join(', ') }}</dd>
                        </div>
                    @endif
                    <div class="flex flex-col gap-0.5 border-t border-line pt-4 sm:col-span-2">
                        <dt class="text-sm text-muted">Prices and availability</dt>
                        <dd class="text-sm font-semibold text-forest">Confirmed on request</dd>
                    </div>
                </dl>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" :href="$enquiryHref" icon="chat-bubble-left-right">Enquire about this location</x-ui.button>
                    @if ($mapHref)
                        <x-ui.button size="lg" variant="secondary" :href="$mapHref" icon="map" rel="noopener">Open map</x-ui.button>
                    @endif
                </div>
                <p class="text-sm text-muted">An enquiry does not reserve anything. SmartGro replies with options, prices and availability in writing.</p>
            </div>
        </div>
    </section>

    @if (count($sections) > 1)
        <nav aria-label="On this page" class="sticky top-18 z-30 hidden border-b border-line bg-paper/95 backdrop-blur lg:top-20 lg:block">
            <ul class="container-page flex gap-6 overflow-x-auto text-sm font-semibold text-muted">
                @foreach ($sections as $anchor => $label)
                    <li><a href="#{{ $anchor }}" class="inline-block py-3.5 hover:text-leaf-700">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        @if (filled($profile['summary'] ?? null))
            <p class="max-w-3xl text-lead text-pretty text-ink">{{ $profile['summary'] }}</p>
        @endif

        <section id="workspaces" aria-labelledby="workspaces-heading" class="flex scroll-mt-36 flex-col gap-6">
            <x-ui.section-heading eyebrow="Workspaces" :title="'Workspaces at SmartGro '.$property->name" id="workspaces-heading" />
            @if ($offerings !== [])
                <ul class="flex flex-col gap-4">
                    @foreach ($offerings as $item)
                        <li>
                            <x-card.offering
                                :name="$item['profile']['headline'] ?? $item['offering']->product->name"
                                :category="$item['offering']->product->name"
                                :included="$item['profile']['included']"
                                :not-included="$item['profile']['notIncluded']"
                                action="See details"
                                :action-href="$preview === null ? $item['offering']->path() : '#'"
                            />
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-col items-start gap-4 rounded-2xl bg-surface p-6 ring-1 ring-line">
                    <p class="text-muted">Ask SmartGro which workspaces are available at this location.</p>
                    <x-ui.button variant="secondary" :href="$enquiryHref" icon="chat-bubble-left-right">Enquire</x-ui.button>
                </div>
            @endif
        </section>

        @if (count($photos) > 1)
            <section id="photos" aria-labelledby="photos-heading" class="flex scroll-mt-36 flex-col gap-6">
                <x-ui.section-heading eyebrow="Photos" :title="'Inside SmartGro '.$property->name" id="photos-heading" />
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (array_slice($photos, 1) as $photo)
                        <li>
                            <figure class="flex flex-col gap-2">
                                <img src="{{ $photo['src'] }}" srcset="{{ $photo['srcset'] }}" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" alt="{{ $photo['alt'] }}"
                                    @if ($photo['dimensions']) width="{{ $photo['dimensions']['width'] }}" height="{{ $photo['dimensions']['height'] }}" @endif
                                    loading="lazy" decoding="async" class="aspect-[4/3] h-auto w-full rounded-2xl bg-mist object-cover">
                                @if (filled($photo['credit']))
                                    <figcaption class="text-sm text-muted">{{ $photo['credit'] }}</figcaption>
                                @endif
                            </figure>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($hasGettingHere || $openingHours !== [])
            <div class="grid gap-10 lg:grid-cols-12">
                @if ($hasGettingHere)
                    <section id="getting-here" aria-labelledby="getting-here-heading" class="flex scroll-mt-36 flex-col gap-4 lg:col-span-7">
                        <x-ui.section-heading eyebrow="Getting here" :title="$property->addressLine()" id="getting-here-heading" />
                        @if (filled($profile['gettingThere'] ?? null))
                            <p class="max-w-2xl text-pretty text-ink">{{ $profile['gettingThere'] }}</p>
                        @endif
                        @if ($gettingHere !== [])
                            <dl class="grid max-w-2xl gap-x-6 gap-y-3 sm:grid-cols-[auto_1fr]">
                                @foreach ($gettingHere as $detail)
                                    <dt class="text-sm font-semibold text-forest">{{ $detail['label'] }}</dt>
                                    <dd class="text-pretty text-ink">{{ $detail['text'] }}</dd>
                                @endforeach
                            </dl>
                        @endif
                        @if ($mapHref)
                            <x-ui.button variant="secondary" :href="$mapHref" icon="map" rel="noopener" class="self-start">Open the location on a map</x-ui.button>
                        @endif
                    </section>
                @endif
                @if ($openingHours !== [])
                    <section id="hours" aria-labelledby="hours-heading" @class(['flex scroll-mt-36 flex-col gap-3 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line', 'lg:col-span-5' => $hasGettingHere, 'lg:col-span-6' => ! $hasGettingHere])>
                        <h2 id="hours-heading" class="font-display text-subheading font-semibold text-forest">Opening hours</h2>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                            @foreach ($openingHours as $day)
                                <dt class="font-semibold text-forest">{{ $day['day'] }}</dt>
                                <dd class="text-muted">{{ $day['hours'] }}</dd>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </div>
        @endif

        @if (count($nearby) > 0)
            <section id="nearby" aria-labelledby="nearby-heading" class="flex scroll-mt-36 flex-col gap-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <x-ui.section-heading eyebrow="Nearby" :title="'Other SmartGro locations in '.$property->city->name" id="nearby-heading" />
                    @if ($preview === null)
                        <x-ui.button variant="secondary" :href="route('locations.compare', ['locations' => [$property->city->slug.'/'.$property->slug]])" icon="table-cells" class="self-start sm:self-auto">Compare locations</x-ui.button>
                    @endif
                </div>
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($nearby as $other)
                        <li><x-site.location-card :property="$other" class="h-full" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <x-site.contact-section :contact="$contact['details']" :title="$contact['title']" :intro="$contact['intro']" />
</x-layouts::site>
