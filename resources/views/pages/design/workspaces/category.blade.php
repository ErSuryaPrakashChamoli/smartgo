<x-layouts::site
    :title="$category['name'].' (design preview)'"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    @php
        $primaryHref = match (true) {
            $category['slug'] === 'managed-offices' => route('design.enterprise'),
            $category['workflow'] === 'request_then_approve' => route('design.flow', 'request-to-book'),
            default => route('design.flow', 'enquiry'),
        };
        $related = collect($categories)->reject(fn ($other) => $other['slug'] === $category['slug'])->take(3);
    @endphp

    <section class="border-b border-line bg-surface">
        <div class="container-page grid gap-8 py-10 lg:grid-cols-12 lg:gap-12 lg:py-14">
            <div class="flex flex-col gap-5 lg:col-span-7">
                <x-site.breadcrumb :items="[['label' => 'Workspaces', 'href' => route('design.workspaces')], ['label' => $category['name']]]" />
                <span class="flex size-14 items-center justify-center rounded-2xl bg-mist text-leaf-700">
                    <x-dynamic-component :component="'heroicon-o-'.$category['icon']" class="size-7" aria-hidden="true" />
                </span>
                <h1 class="font-display text-display-xl font-semibold text-forest">{{ $category['name'] }}</h1>
                <p class="max-w-xl text-lead text-muted">{{ $category['summary'] }}</p>
                <div class="flex flex-wrap gap-2">
                    <x-ui.badge tone="brand" icon="calendar-days">{{ $category['fulfilment'] }}</x-ui.badge>
                    <x-ui.badge tone="neutral" icon="arrow-path">{{ $category['workflow'] === 'request_then_approve' ? 'Request, then SmartGro confirms' : 'Enquiry, then a proposal' }}</x-ui.badge>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" :href="$primaryHref" icon="arrow-right">{{ $category['primaryAction'] }}</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" :href="route('design.flow', 'tour')">Book a tour</x-ui.button>
                </div>
            </div>
            <div class="flex flex-col gap-4 lg:col-span-5">
                <x-ui.photo class="aspect-[4/3] rounded-3xl" :label="$category['name'].' photography to be supplied by SmartGro'" />
                <div class="flex flex-col gap-1 rounded-2xl bg-paper p-5 ring-1 ring-line">
                    <p class="text-sm text-muted">Price per {{ $category['priceUnit'] }}</p>
                    <x-ui.price />
                </div>
            </div>
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <section aria-labelledby="how-heading" class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="How it works" :title="'Getting started with '.strtolower($category['name'])" />
            </div>
            <ol class="grid gap-4 sm:grid-cols-2 lg:col-span-8">
                @foreach ($category['steps'] as $step)
                    <li class="flex gap-4 rounded-2xl bg-surface p-5 ring-1 ring-line">
                        <span class="font-display text-heading font-semibold text-leaf-600">0{{ $loop->iteration }}</span>
                        <span class="pt-1 font-semibold text-forest">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section aria-labelledby="ask-heading" class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="Before you start" title="What we will ask you" />
            </div>
            <ul class="flex flex-wrap content-start gap-2 lg:col-span-8">
                @foreach ($category['requiredDetails'] as $detail)
                    <li class="inline-flex items-center gap-2 rounded-full bg-surface px-4 py-2 text-sm font-medium text-ink ring-1 ring-line">
                        <x-heroicon-m-check class="size-4 text-leaf-700" aria-hidden="true" />{{ $detail }}
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="where-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Where" :title="'Where to find '.strtolower($category['name'])" />
            <x-ui.empty-state icon="map-pin" title="Properties are listed once inventory is published">
                Each property offering {{ strtolower($category['name']) }} appears here with its availability, capacity and price display.
                <x-slot:action>
                    <x-ui.button variant="secondary" size="sm" :href="route('design.sample-property')">See an offering on the sample property</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
            <x-site.design-note title="When this page is published">
                In production a workspace page exists only while at least one published property offers it (BR-PRODCFG-4). Steps, the details asked and the main action follow the category's settings in the back office.
            </x-site.design-note>
        </section>

        <section aria-labelledby="category-faqs" class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="FAQs" :title="$category['name'].' questions'" />
            </div>
            <div class="lg:col-span-8">
                @foreach (['What is included?', 'How long is the minimum term or booking?', 'Can I change or cancel?'] as $question)
                    <x-ui.faq-item :question="$question" :class="$loop->first ? 'border-t' : ''">Answer to be supplied by SmartGro.</x-ui.faq-item>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="related-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Also consider" title="Other workspace types" />
            <ul class="grid gap-4 sm:grid-cols-3">
                @foreach ($related as $other)
                    <li><x-card.workspace :name="$other['name']" :icon="$other['icon']" :href="route('design.workspace', $other['slug'])" class="h-full">{{ $other['summary'] }}</x-card.workspace></li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts::site>
