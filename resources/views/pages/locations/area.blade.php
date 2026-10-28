{{-- One sector of a city (INFORMATION_ARCHITECTURE §2, FR-DISC-1): its properties on the website, from published records only. The page exists only while at least one is listed. --}}
<x-layouts::site
    :title="'Workspaces in '.$sector->name.', '.$city->name"
    :description="'SmartGro workspace locations in '.$sector->name.', '.$city->name.'.'"
    :canonical="$canonical"
    :navigation="$navigation"
>
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => '/locations'], ['label' => $city->name, 'href' => '/locations/'.$city->slug], ['label' => $sector->name]]" />
        <div class="flex max-w-3xl flex-col gap-3">
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Workspaces in {{ $sector->name }}, {{ $city->name }}</h1>
            <p class="text-lead text-pretty text-muted">{{ trans_choice(':count SmartGro location|:count SmartGro locations', $properties->count()) }} in {{ $sector->name }}.</p>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($properties as $property)
                <li><x-site.location-card :property="$property" class="h-full" /></li>
            @endforeach
        </ul>

        <div class="flex flex-col items-start gap-4 rounded-2xl bg-mist p-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-ink">Not sure which location suits your team? Ask SmartGro. An enquiry does not reserve anything.</p>
            <x-ui.button :href="route('enquiries.create')" icon="chat-bubble-left-right" class="shrink-0">Enquire</x-ui.button>
        </div>

        @if ($otherSectors->isNotEmpty())
            <section aria-labelledby="other-areas-heading" class="flex flex-col gap-4">
                <h2 id="other-areas-heading" class="font-display text-subheading font-semibold text-forest">Other areas in {{ $city->name }}</h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($otherSectors as $other)
                        <li><a href="{{ $other->path() }}" class="inline-flex rounded-full bg-surface px-4 py-2 text-sm font-semibold text-forest ring-1 ring-line hover:text-leaf-700 hover:ring-leaf-700">{{ $other->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <x-site.contact-section :contact="$contact" />
</x-layouts::site>
