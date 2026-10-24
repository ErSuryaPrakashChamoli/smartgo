<x-layouts::site
    title="Workspaces (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    @php($workflowLabels = ['enquiry_quote' => 'Enquiry, then a proposal', 'request_then_approve' => 'Send a request; SmartGro confirms'])

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <div class="flex flex-col gap-4">
            <x-site.breadcrumb :items="[['label' => 'Home', 'href' => route('design.home')], ['label' => 'Workspaces']]" />
            <x-ui.section-heading level="h1" eyebrow="Workspaces" title="Seven ways to work with SmartGro">
                From a desk for the day to an office suite run for your company. Compare how each one works, then take the next step.
            </x-ui.section-heading>
        </div>

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($categories as $category)
                <li @class(['lg:col-span-2' => $loop->last])>
                    <x-card.workspace :name="$category['name']" :icon="$category['icon']" :href="route('design.workspace', $category['slug'])" class="h-full">{{ $category['summary'] }}</x-card.workspace>
                </li>
            @endforeach
        </ul>

        <section aria-labelledby="compare-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Compare" title="How each workspace works" />
            <div class="overflow-x-auto rounded-2xl bg-surface ring-1 ring-line">
                <table class="w-full min-w-[46rem] text-left text-sm">
                    <caption class="sr-only">Comparison of the seven workspace types</caption>
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Workspace type</th>
                            <th scope="col" class="px-5 py-3 font-semibold">How you use it</th>
                            <th scope="col" class="px-5 py-3 font-semibold">How to get started</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($categories as $category)
                            <tr>
                                <th scope="row" class="px-5 py-4 font-semibold text-forest">
                                    <a href="{{ route('design.workspace', $category['slug']) }}" class="hover:text-leaf-700 hover:underline">{{ $category['name'] }}</a>
                                </th>
                                <td class="px-5 py-4 text-ink">{{ $category['fulfilment'] }}</td>
                                <td class="px-5 py-4 text-ink">{{ $workflowLabels[$category['workflow']] }}</td>
                                <td class="px-5 py-4"><x-ui.price /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-site.design-note title="Workflows are proposed defaults">
                The "how to get started" column uses the proposed workflow for each category (DATABASE_ERD §4.1); administrators change it per category. Instant online booking is offered only after the payment decisions D-07 and D-08 (BR-PRODCFG-5). Prices show once SmartGro approves them and decides whether to publish them (D-04).
            </x-site.design-note>
        </section>

        <x-site.cta-band eyebrow="Not sure?" title="Tell us how your team works">
            We will suggest the workspace types and properties that fit, and arrange a visit.
            <x-slot:actions>
                <x-ui.button variant="inverse" size="lg" :href="route('design.flow', 'enquiry')">Enquire</x-ui.button>
                <x-ui.button variant="inverse-outline" size="lg" :href="route('design.flow', 'tour')">Book a tour</x-ui.button>
            </x-slot:actions>
        </x-site.cta-band>
    </div>
</x-layouts::site>
