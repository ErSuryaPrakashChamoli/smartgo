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
                ['route' => 'design.foundations', 'icon' => 'swatch', 'title' => 'Foundations', 'text' => 'Colours from the logo, contrast checks, typography and logo usage.'],
                ['route' => 'design.components', 'icon' => 'squares-plus', 'title' => 'Components', 'text' => 'Buttons, forms, cards, messages and their states.'],
                ['route' => 'design.home', 'icon' => 'home-modern', 'title' => 'Home page', 'text' => 'The visual-direction sample for sign-off.'],
            ] as $page)
                <li class="relative flex flex-col gap-3 rounded-2xl bg-surface p-6 ring-1 ring-line hover:shadow-card">
                    <x-dynamic-component :component="'heroicon-o-'.$page['icon']" class="size-6 text-leaf-700" aria-hidden="true" />
                    <h2 class="font-display text-subheading font-semibold text-forest">
                        <a href="{{ route($page['route']) }}" class="rounded-sm after:absolute after:inset-0">{{ $page['title'] }}</a>
                    </h2>
                    <p class="text-sm text-muted">{{ $page['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::site>
