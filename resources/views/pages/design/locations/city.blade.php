<x-layouts::site
    :title="'Coworking in '.$city['name'].' (design preview)'"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <section class="border-b border-line bg-surface">
        <div class="container-page flex flex-col gap-6 py-10 lg:py-14">
            <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => route('design.locations')], ['label' => $city['name']]]" />
            <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
                <div class="flex flex-col gap-4 lg:col-span-8">
                    <p class="text-eyebrow font-semibold text-leaf-700 uppercase">{{ $city['name'] }}, Delhi NCR</p>
                    <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Coworking and office spaces in {{ $city['name'] }}</h1>
                    <p class="max-w-2xl text-lead text-muted">
                        {{ array_sum($areas) }} SmartGro properties across {{ \Illuminate\Support\Arr::join(array_keys($areas), ', ', ' and ') }}. Compare them, then visit the one that suits your team.
                    </p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:col-span-4 lg:justify-end">
                    <x-ui.button size="lg" :href="route('design.flow', 'tour')" icon="calendar-days">Book a tour</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" :href="route('design.flow', 'enquiry')">Enquire</x-ui.button>
                </div>
            </div>
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-14">
        <section aria-labelledby="properties-heading" class="flex flex-col gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="properties-heading" class="font-display text-heading font-semibold text-forest">
                    {{ $selectedArea ? 'Properties in '.$selectedArea : 'All properties' }}
                    <span class="font-sans text-base font-normal text-muted">({{ count($cityProperties) }})</span>
                </h2>
                <nav aria-label="Filter by area" class="-mx-5 overflow-x-auto px-5 sm:mx-0 sm:px-0">
                    <ul class="flex gap-2">
                        <li>
                            <a href="{{ route('design.city', $city['slug']) }}" @if (! $selectedArea) aria-current="page" @endif @class(['inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold whitespace-nowrap ring-1 ring-inset', 'bg-forest text-paper ring-forest' => ! $selectedArea, 'bg-surface text-forest ring-control hover:bg-mist' => $selectedArea])>All areas</a>
                        </li>
                        @foreach ($areas as $area => $count)
                            <li>
                                <a href="{{ route('design.city', [$city['slug'], 'area' => $area]) }}" @if ($selectedArea === $area) aria-current="page" @endif @class(['inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold whitespace-nowrap ring-1 ring-inset', 'bg-forest text-paper ring-forest' => $selectedArea === $area, 'bg-surface text-forest ring-control hover:bg-mist' => $selectedArea !== $area])>{{ $area }} · {{ $count }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>
            <div class="grid gap-6 lg:grid-cols-12">
                <ul class="grid gap-5 sm:grid-cols-2 lg:col-span-8">
                    @foreach ($cityProperties as $property)
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
                <details class="group rounded-2xl bg-surface ring-1 ring-line lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl p-4 font-semibold text-forest [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2"><x-heroicon-o-map class="size-5 text-leaf-700" aria-hidden="true" /> Show map</span>
                        <x-heroicon-m-chevron-down class="size-5 transition-transform group-open:rotate-180" aria-hidden="true" />
                    </summary>
                    <x-ui.placeholder icon="map" label="Map of these properties (added with the map provider in Phase 6)" class="m-4 mt-0 h-64" />
                </details>
                <div class="hidden lg:col-span-4 lg:block">
                    <x-ui.placeholder icon="map" label="Map of these properties (added with the map provider in Phase 6)" class="sticky top-28 h-[28rem]" />
                </div>
            </div>
        </section>

        <section aria-labelledby="types-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Workspaces" :title="'Workspace types in '.$city['name']" />
            <ul class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('design.workspace', $category['slug']) }}" class="inline-flex items-center gap-2 rounded-full bg-surface px-4 py-2.5 text-sm font-semibold text-forest ring-1 ring-line hover:ring-leaf-300">
                            <x-dynamic-component :component="'heroicon-o-'.$category['icon']" class="size-4 text-leaf-700" aria-hidden="true" />
                            {{ $category['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <x-site.design-note title="Category and city pages">
                In production a workspace type is linked here only when at least one {{ $city['name'] }} property offers it (BR-PRODCFG-4). Which properties offer which types is not known yet, so this preview links every type.
            </x-site.design-note>
        </section>

        <x-site.cta-band eyebrow="Managed offices" :title="'Planning space for a larger team in '.$city['name'].'?'">
            Tell us your seat count, timeline and preferred area, and we will prepare options.
            <x-slot:actions>
                <x-ui.button variant="inverse" size="lg" :href="route('design.enterprise')">Talk to us</x-ui.button>
            </x-slot:actions>
        </x-site.cta-band>

        <section aria-labelledby="city-faqs" class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="FAQs" :title="'Working in '.$city['name']" />
            </div>
            <div class="lg:col-span-8">
                @foreach (['Which area of '.$city['name'].' suits my team?', 'Is parking available at the properties?', 'How close are the properties to the metro?'] as $question)
                    <x-ui.faq-item :question="$question" :class="$loop->first ? 'border-t' : ''">Answer to be supplied by SmartGro.</x-ui.faq-item>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts::site>
