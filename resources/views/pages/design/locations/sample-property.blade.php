<x-layouts::site
    title="Sample property (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page pt-8">
        <x-ui.alert type="warning" title="Sample property: every value on this page is illustrative">
            This fictional property shows the property template fully populated. It is not a SmartGro location, and none of its spaces, capacities, availability or amenities are real. Prices stay "on request" until SmartGro approves them.
        </x-ui.alert>
    </div>

    <section class="border-b border-line">
        <div class="container-page grid gap-8 py-8 lg:grid-cols-12 lg:gap-12 lg:py-12">
            <div class="grid grid-cols-4 gap-3 lg:col-span-7">
                <x-ui.photo class="col-span-4 aspect-[4/3] rounded-2xl" label="Photo placeholder" />
                @foreach (range(1, 3) as $thumbnail)
                    <x-ui.photo class="hidden aspect-square rounded-xl sm:flex" label="Photo placeholder" />
                @endforeach
                <div class="hidden aspect-square items-center justify-center rounded-xl bg-forest text-center text-sm font-semibold text-paper sm:flex">+ 12 photos</div>
            </div>
            <div class="flex flex-col gap-5 lg:col-span-5">
                <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => route('design.locations')], ['label' => 'Sample city', 'href' => '#'], ['label' => 'Sample property']]" />
                <div class="flex flex-col gap-2">
                    <p class="flex items-center gap-1.5 text-sm text-muted">
                        <x-heroicon-m-map-pin class="size-4 text-leaf-700" aria-hidden="true" />
                        Sample area, Sample city
                    </p>
                    <h1 class="font-display text-display-xl font-semibold text-forest">Sample property</h1>
                    <x-ui.badge tone="success" icon="check-circle" class="self-start">Open</x-ui.badge>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 rounded-2xl bg-surface p-5 ring-1 ring-line">
                    @foreach ([['Opening hours', 'Mon–Sat, 09:00–20:00'], ['Floors', '3 floors'], ['Workspace types', '5 types'], ['Availability', 'Desks and cabins available']] as [$term, $value])
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-sm text-muted">{{ $term }}</dt>
                            <dd class="font-semibold text-forest">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" :href="route('design.flow', 'tour')" icon="calendar-days">Book a tour</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" icon="cube-transparent" href="#explore">Explore in 3D</x-ui.button>
                </div>
            </div>
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <section aria-labelledby="sample-workspaces" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Workspaces" title="Workspaces at Sample property" />
            <div class="flex flex-col gap-4">
                <x-card.offering name="Sample cabin" category="Private cabins" capacity="4–6 people" availability="available" price-unit="month" action="Request a proposal" :action-href="route('design.flow', 'enquiry')"
                    :included="['Furniture', 'Lockable door', 'Meeting room credits']" :not-included="['Dedicated parking']" />
                <x-card.offering name="Sample desk zone" category="Dedicated desks" capacity="Per desk" availability="limited" price-unit="desk per month" action="Request a proposal" :action-href="route('design.flow', 'enquiry')"
                    :included="['Your own desk', 'Storage pedestal']" :not-included="['Private room']" />
                <x-card.offering name="Sample flexible zone" category="Hot desks" capacity="Any open desk" availability="available-from" available-from="1 Dec" price-unit="day" action="Request a day pass" :action-href="route('design.flow', 'request-to-book')"
                    :included="['Any open desk', 'Shared lounge']" :not-included="['Fixed desk', 'Storage']" />
                <x-card.offering name="Sample meeting room" category="Meeting rooms" capacity="Up to 8 people" availability="available" price-unit="hour" action="Request a booking" :action-href="route('design.flow', 'request-to-book')"
                    :included="['Screen', 'Whiteboard']" :not-included="['Catering']" />
                <x-card.offering name="Sample training room" category="Conference and training rooms" capacity="Up to 24 people" availability="waitlist" price-unit="half day" action="Join waitlist" :action-href="route('design.flow', 'enquiry')"
                    :included="['Projector', 'Flexible layout']" :not-included="['Catering']" />
            </div>
        </section>

        <section id="explore" aria-labelledby="sample-explore" class="flex scroll-mt-36 flex-col gap-6">
            <x-ui.section-heading eyebrow="Explore" title="Floors and spaces" />
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.placeholder icon="cube-transparent" label="3D scene (Phase 7)" class="aspect-[16/10]" />
                <x-ui.placeholder icon="map" label="Interactive floor plan with an accessible list of spaces" class="aspect-[16/10]" />
            </div>
        </section>

        <section aria-labelledby="sample-amenities" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Amenities" title="What's included" />
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['Standard' => ['High-speed internet', 'Reception', 'Housekeeping'], 'Only at this property' => ['Terrace', 'Phone booths'], 'Not included' => ['Dedicated parking', 'Catering']] as $group => $items)
                    <div class="rounded-2xl bg-surface p-5 ring-1 ring-line">
                        <p class="font-semibold text-forest">{{ $group }}</p>
                        <ul class="mt-3 flex flex-col gap-2 text-sm text-ink">
                            @foreach ($items as $item)
                                <li class="flex gap-2">
                                    <x-dynamic-component :component="$group === 'Not included' ? 'heroicon-m-minus' : 'heroicon-m-check'" :class="'mt-0.5 size-4 shrink-0 '.($group === 'Not included' ? 'text-muted' : 'text-available')" aria-hidden="true" />
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="sample-getting-here" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Getting here" title="Sample area, Sample city" />
            <div class="grid gap-6 lg:grid-cols-12">
                <x-ui.placeholder icon="map" label="Map and directions" class="aspect-[16/10] lg:col-span-7 lg:aspect-auto lg:min-h-72" />
                <dl class="flex flex-col divide-y divide-line rounded-2xl bg-surface ring-1 ring-line lg:col-span-5">
                    @foreach ([['map', 'Nearest metro station', 'Sample station · 6 min walk'], ['truck', 'Cab drop-off', 'Main gate'], ['key', 'Parking', 'Paid parking nearby'], ['user-group', 'Accessibility', 'Step-free entrance and lift']] as [$icon, $term, $value])
                        <div class="flex gap-3 p-4">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-0.5 size-5 shrink-0 text-leaf-700" aria-hidden="true" />
                            <div>
                                <dt class="text-sm font-semibold text-forest">{{ $term }}</dt>
                                <dd class="text-sm text-muted">{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    </div>
</x-layouts::site>
