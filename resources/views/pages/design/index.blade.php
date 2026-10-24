<x-layouts::site
    title="Design previews"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page flex flex-col gap-10 py-12 lg:py-16">
        <x-ui.section-heading level="h1" eyebrow="Phase 2" title="SmartGro design previews">
            Work in progress for owner review. These pages are never served in production and are hidden from search engines.
        </x-ui.section-heading>
        <ul class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['url' => route('design.foundations'), 'icon' => 'swatch', 'title' => 'Foundations', 'text' => 'Colours from the logo, contrast checks, typography and logo usage.'],
                ['url' => route('design.components'), 'icon' => 'squares-plus', 'title' => 'Components', 'text' => 'Buttons, forms, cards, messages, journey statuses and their states.'],
                ['url' => route('design.home'), 'icon' => 'home-modern', 'title' => 'Home page', 'text' => 'The visual-direction sample, awaiting the owner\'s sign-off.'],
                ['url' => route('design.locations'), 'icon' => 'map-pin', 'title' => 'Locations', 'text' => 'All cities and properties.'],
                ['url' => route('design.city', 'noida'), 'icon' => 'building-office-2', 'title' => 'City: Noida', 'text' => 'Properties by area, map, workspace types.'],
                ['url' => route('design.city', 'gurugram'), 'icon' => 'clock', 'title' => 'City: coming soon', 'text' => 'Example state for a visible city without a property.'],
                ['url' => route('design.property', ['noida', 'a-70']), 'icon' => 'building-office', 'title' => 'Property: A-70', 'text' => 'The property template with today\'s confirmed data.'],
                ['url' => route('design.sample-property'), 'icon' => 'sparkles', 'title' => 'Sample property', 'text' => 'The property template fully populated with illustrative data.'],
                ['url' => route('design.workspaces'), 'icon' => 'squares-2x2', 'title' => 'Workspaces', 'text' => 'The seven workspace types compared.'],
                ['url' => route('design.workspace', 'meeting-rooms'), 'icon' => 'user-group', 'title' => 'Workspace type', 'text' => 'Category template (all seven use it).'],
                ['url' => route('design.enterprise'), 'icon' => 'briefcase', 'title' => 'Enterprise', 'text' => 'Managed offices and consultation requests.'],
                ['url' => route('design.flow', 'enquiry'), 'icon' => 'chat-bubble-left-right', 'title' => 'Journey: enquiry', 'text' => 'Enquiry first, with its received state.'],
                ['url' => route('design.flow', 'tour'), 'icon' => 'calendar-days', 'title' => 'Journey: tour', 'text' => 'Request, confirmation and conflict states.'],
                ['url' => route('design.flow', 'request-to-book'), 'icon' => 'clipboard-document-list', 'title' => 'Journey: request to book', 'text' => 'Today\'s booking workflow, confirmed by SmartGro.'],
                ['url' => route('design.flow', 'online-booking'), 'icon' => 'credit-card', 'title' => 'Journey: online booking', 'text' => 'Hold, payment and outcomes (after D-07/D-08).'],
            ] as $page)
                <li class="relative flex flex-col gap-3 rounded-2xl bg-surface p-6 ring-1 ring-line hover:shadow-card">
                    <x-dynamic-component :component="'heroicon-o-'.$page['icon']" class="size-6 text-leaf-700" aria-hidden="true" />
                    <h2 class="font-display text-subheading font-semibold text-forest">
                        <a href="{{ $page['url'] }}" class="rounded-sm after:absolute after:inset-0">{{ $page['title'] }}</a>
                    </h2>
                    <p class="text-sm text-muted">{{ $page['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::site>
