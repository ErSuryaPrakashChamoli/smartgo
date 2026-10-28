{{-- All workspace types (IA §2, the approved hub design). Each type links to its page only while a published offering exists (BR-PRODCFG-4); the others link to the enquiry form. No prices are shown. --}}
<x-layouts::site title="Workspaces" description="Private offices and cabins, dedicated and hot desks, meeting and conference rooms, virtual offices and managed offices at SmartGro in Noida." :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <div class="flex flex-col gap-4">
            <x-site.breadcrumb :items="[['label' => 'Home', 'href' => '/'], ['label' => 'Workspaces']]" />
            <x-ui.section-heading level="h1" eyebrow="Workspaces" title="Space for every way of working">
                From a desk of your own to an office run for your company. Choose a workspace type to see where SmartGro offers it, or ask us.
            </x-ui.section-heading>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($categories as $category)
                <li>
                    <x-card.workspace :name="$category['name']" :icon="$category['icon'] ?? 'squares-2x2'" :href="$category['path'] ?? route('enquiries.create', ['workspace' => $category['slug']])" class="h-full">
                        {{ $category['summary'] }}
                        @if ($category['path'] === null)
                            <span class="mt-2 block text-sm font-medium text-ink">Ask SmartGro where this is available.</span>
                        @endif
                    </x-card.workspace>
                </li>
            @endforeach
        </ul>

        <x-site.cta-band eyebrow="Not sure?" title="Tell us how your team works">
            SmartGro will suggest the workspace types and locations that fit. An enquiry does not reserve anything.
            <x-slot:actions>
                <x-ui.button size="lg" variant="inverse" :href="route('enquiries.create')" icon="chat-bubble-left-right">Send an enquiry</x-ui.button>
            </x-slot:actions>
        </x-site.cta-band>
    </div>
</x-layouts::site>
