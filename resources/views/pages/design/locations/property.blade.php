<x-layouts::site
    :title="$property['name'].', '.$property['area'].' (design preview)'"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    {{-- Property page: the sales kit (IA §3.1). Only the address is confirmed; everything else is a labelled placeholder. --}}
    <section class="border-b border-line bg-surface">
        <div class="container-page grid gap-8 py-8 lg:grid-cols-12 lg:gap-12 lg:py-12">
            <div class="grid grid-cols-4 gap-3 lg:col-span-7">
                <x-ui.photo class="col-span-4 aspect-[4/3] rounded-2xl" label="Property photography to be supplied by SmartGro" />
                @foreach (range(1, 3) as $thumbnail)
                    <x-ui.photo class="hidden aspect-square rounded-xl sm:flex" label="Photo to be supplied" />
                @endforeach
                <div class="hidden aspect-square items-center justify-center rounded-xl bg-forest text-center text-sm font-semibold text-paper sm:flex">Gallery</div>
            </div>
            <div class="flex flex-col gap-5 lg:col-span-5">
                <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => route('design.locations')], ['label' => $property['city'], 'href' => route('design.city', $property['citySlug'])], ['label' => $property['name'].', '.$property['area']]]" />
                <div class="flex flex-col gap-2">
                    <p class="flex items-center gap-1.5 text-sm text-muted">
                        <x-heroicon-m-map-pin class="size-4 text-leaf-700" aria-hidden="true" />
                        {{ $property['area'] }}, {{ $property['city'] }}
                    </p>
                    <h1 class="font-display text-display-xl font-semibold text-forest">{{ $property['name'] }}, {{ $property['area'] }}</h1>
                    <x-ui.badge tone="neutral" icon="information-circle" class="self-start">Opening status to be confirmed</x-ui.badge>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 rounded-2xl bg-paper p-5 ring-1 ring-line">
                    @foreach ([
                        ['Address', $property['name'].', '.$property['area'].', '.$property['city'], true],
                        ['Opening hours', 'To be confirmed', false],
                        ['Floors', 'To be confirmed', false],
                        ['Workspace types', 'To be confirmed', false],
                    ] as [$term, $value, $isConfirmed])
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-sm text-muted">{{ $term }}</dt>
                            <dd @class(['font-semibold', 'text-forest' => $isConfirmed, 'text-muted italic' => ! $isConfirmed])>{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="col-span-2 flex flex-col gap-0.5 border-t border-line pt-4">
                        <dt class="text-sm text-muted">Pricing</dt>
                        <dd><x-ui.price /></dd>
                    </div>
                </dl>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" href="#book-a-tour" icon="calendar-days">Book a tour</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" :href="route('design.flow', 'enquiry')">Enquire</x-ui.button>
                </div>
                <p class="flex items-center gap-1.5 text-sm text-muted">
                    <x-heroicon-m-cube-transparent class="size-4" aria-hidden="true" />
                    3D tour: shown here once a scene is published (Phase 7).
                </p>
            </div>
        </div>
    </section>

    <nav aria-label="On this page" class="sticky top-18 z-30 hidden border-b border-line bg-paper/95 backdrop-blur lg:top-20 lg:block">
        <ul class="container-page flex gap-6 overflow-x-auto text-sm font-semibold text-muted">
            @foreach (['workspaces' => 'Workspaces', 'explore' => 'Explore', 'amenities' => 'Amenities', 'getting-here' => 'Getting here', 'faqs' => 'FAQs', 'book-a-tour' => 'Book a tour'] as $anchor => $label)
                <li><a href="#{{ $anchor }}" class="inline-block py-3.5 hover:text-leaf-700">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <section id="workspaces" aria-labelledby="workspaces-heading" class="flex scroll-mt-36 flex-col gap-6">
            <x-ui.section-heading eyebrow="Workspaces" :title="'Workspaces at '.$property['name']" />
            <x-ui.empty-state icon="squares-2x2" title="Workspace details are not listed yet">
                They appear here, with live availability, once SmartGro enters this property's inventory.
                <x-slot:action>
                    <x-ui.button variant="secondary" size="sm" :href="route('design.sample-property')">See the populated layout on the sample property</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </section>

        <section id="explore" aria-labelledby="explore-heading" class="flex scroll-mt-36 flex-col gap-6">
            <x-ui.section-heading eyebrow="Explore" title="Floors and spaces" />
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.placeholder icon="cube-transparent" label="3D scene of this property (Phase 7, when SmartGro publishes a scan)" class="aspect-[16/10]" />
                <x-ui.placeholder icon="map" label="Interactive floor plan with an accessible list of spaces (when floors are confirmed)" class="aspect-[16/10]" />
            </div>
        </section>

        <section id="amenities" aria-labelledby="amenities-heading" class="flex scroll-mt-36 flex-col gap-6">
            <x-ui.section-heading eyebrow="Amenities" title="What's included" />
            <x-ui.empty-state icon="sparkles" title="Amenities to be supplied by SmartGro">
                Listed as standard, unique to this property, and not included, so visitors know exactly what to expect.
            </x-ui.empty-state>
        </section>

        <section id="getting-here" aria-labelledby="getting-here-heading" class="flex scroll-mt-36 flex-col gap-6">
            <x-ui.section-heading eyebrow="Getting here" :title="$property['name'].', '.$property['area'].', '.$property['city']" />
            <div class="grid gap-6 lg:grid-cols-12">
                <x-ui.placeholder icon="map" label="Map and directions (Phase 6)" class="aspect-[16/10] lg:col-span-7 lg:aspect-auto lg:min-h-80" />
                <dl class="flex flex-col divide-y divide-line rounded-2xl bg-surface ring-1 ring-line lg:col-span-5">
                    @foreach ([
                        ['map', 'Nearest metro station', 'Station and walking time to be confirmed'],
                        ['truck', 'Cab drop-off', 'To be confirmed'],
                        ['key', 'Parking', 'To be confirmed'],
                        ['user-group', 'Accessibility', 'Step-free access details to be confirmed'],
                    ] as [$icon, $term, $value])
                        <div class="flex gap-3 p-4">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-0.5 size-5 shrink-0 text-leaf-700" aria-hidden="true" />
                            <div>
                                <dt class="text-sm font-semibold text-forest">{{ $term }}</dt>
                                <dd class="text-sm text-muted italic">{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section id="faqs" aria-labelledby="property-faqs" class="grid scroll-mt-36 gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="FAQs" :title="'About '.$property['name']" />
            </div>
            <div class="lg:col-span-8">
                @foreach (['What are the opening hours?', 'Can visitors and guests come in?', 'Is there parking?'] as $question)
                    <x-ui.faq-item :question="$question" :class="$loop->first ? 'border-t' : ''">Answer to be supplied by SmartGro.</x-ui.faq-item>
                @endforeach
            </div>
        </section>

        <section id="book-a-tour" aria-labelledby="tour-heading" class="grid scroll-mt-36 gap-8 rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-10 lg:grid-cols-12">
            <div class="flex flex-col gap-3 lg:col-span-4">
                <x-ui.section-heading eyebrow="Visit" :title="'Book a tour of '.$property['name']" level="h2">
                    See the workspaces in person before you decide.
                </x-ui.section-heading>
            </div>
            <x-site.tour-request-form :id-prefix="'tour-'.$property['slug']" class="lg:col-span-8" />
        </section>

        <section aria-labelledby="nearby-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Nearby" :title="'Other SmartGro properties in '.$property['city']" />
            <ul class="grid gap-5 sm:grid-cols-3">
                @foreach ($nearby as $other)
                    <li>
                        <x-card.property :name="$other['name']" :area="$other['area']" :city="$other['city']" :href="route('design.property', [$other['citySlug'], $other['slug']])" class="h-full" />
                    </li>
                @endforeach
            </ul>
        </section>

        <x-site.design-note title="Sections that appear only with real content">
            Reviews appear only when they are verified and consented (BR-CMS-7). Meeting rooms, downloadable brochures and "Explore in 3D" appear once the matching records exist. Placeholders in italics are replaced by the property's own data.
        </x-site.design-note>
    </div>
</x-layouts::site>
