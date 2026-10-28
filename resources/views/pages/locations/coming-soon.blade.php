{{-- A city switched visible before any SmartGro location there is on the website (OD-22, BR-PROP-4, the approved design): no properties, prices or inventory, not indexed, with a register-interest form once enquiries are open. --}}
<x-layouts::site :title="'SmartGro in '.$city->name" :description="'There is no SmartGro location in '.$city->name.' yet. Register your interest.'" :noindex="true" :navigation="$navigation">
    <div class="container-page">
        <div class="grid gap-10 py-10 lg:grid-cols-12 lg:gap-14 lg:py-16">
            <div class="flex flex-col gap-6 lg:col-span-5">
                <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => '/locations'], ['label' => $city->name]]" />
                <x-ui.badge tone="info" icon="clock" class="self-start">Coming soon</x-ui.badge>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">SmartGro in {{ $city->name }}</h1>
                <p class="text-lead text-muted">There is no SmartGro workspace in {{ $city->name }} yet. Register your interest and SmartGro will let you know if one opens here.</p>
                <x-ui.button variant="secondary" href="/locations" icon="arrow-right" class="self-start">See where SmartGro operates</x-ui.button>
            </div>
            <div class="flex flex-col gap-6 lg:col-span-7">
                @if ($notice !== null)
                    <div class="rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-8">
                        <h2 class="mb-5 font-display text-heading font-semibold text-forest">Register your interest</h2>
                        <x-site.enquiry-form :segments="$segments" :team-sizes="$teamSizes" :selected="['city' => $city->slug]" :started="$started" :notice="$notice" :city-name="$city->name" />
                    </div>
                @else
                    <x-ui.alert type="info" title="Registering interest opens soon">
                        The form opens once SmartGro publishes its privacy notice.
                    </x-ui.alert>
                @endif
            </div>
        </div>
    </div>

    <x-site.contact-section :contact="$contact" />
</x-layouts::site>
