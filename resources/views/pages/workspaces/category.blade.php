{{-- A workspace type (IA §3.3, the approved category design), optionally in one city: only the published offerings of that type. It exists only while one does (BR-PRODCFG-4). No capacity, availability or price is shown. --}}
<x-layouts::site
    :title="$product->name.($city ? ' in '.$city->name : '')"
    :description="($copy['summary'] ?? $product->name).' Available at '.count($offerings).' SmartGro '.(count($offerings) === 1 ? 'location' : 'locations').($city ? ' in '.$city->name : '').'.'"
    :canonical="$canonical"
    :navigation="$navigation"
    :mobile-actions="[['label' => 'Enquire', 'href' => $enquiryHref, 'external' => false]]"
>
    <section class="border-b border-line bg-surface">
        <div class="container-page flex flex-col gap-5 py-10 lg:py-14">
            <x-site.breadcrumb :items="array_values(array_filter([
                ['label' => 'Workspaces', 'href' => '/workspaces'],
                $city ? ['label' => $product->name, 'href' => '/workspaces/'.$product->slug] : null,
                ['label' => $city ? $city->name : $product->name],
            ]))" />
            @if ($copy['icon'])
                <span class="flex size-14 items-center justify-center rounded-2xl bg-mist text-leaf-700">
                    <x-dynamic-component :component="'heroicon-o-'.$copy['icon']" class="size-7" aria-hidden="true" />
                </span>
            @endif
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">{{ $product->name }}{{ $city ? ' in '.$city->name : '' }}</h1>
            @if ($copy['summary'])
                <p class="max-w-2xl text-lead text-muted">{{ $copy['summary'] }}</p>
            @endif
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-ui.button size="lg" :href="$enquiryHref" icon="chat-bubble-left-right">Enquire about {{ \Illuminate\Support\Str::lower($product->name) }}</x-ui.button>
            </div>
            <p class="text-sm text-muted">An enquiry does not reserve anything. Capacity, prices and availability are confirmed in writing.</p>
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <section aria-labelledby="where-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Where" :title="'Where to find '.\Illuminate\Support\Str::lower($product->name)" id="where-heading" />
            @if (! $city && $cities->count() > 1)
                <ul class="flex flex-wrap gap-2" aria-label="By city">
                    @foreach ($cities as $offeringCity)
                        <li><a href="/workspaces/{{ $product->slug }}/{{ $offeringCity->slug }}" class="inline-flex rounded-full bg-surface px-4 py-2 text-sm font-medium text-forest ring-1 ring-line hover:ring-leaf-300">{{ $offeringCity->name }}</a></li>
                    @endforeach
                </ul>
            @endif
            <ul class="flex flex-col gap-4">
                @foreach ($offerings as $item)
                    <li>
                        <x-card.offering
                            :name="'SmartGro '.$item['offering']->property->name.', '.collect([$item['offering']->property->microMarket?->name, $item['offering']->property->city->name])->filter()->join(', ')"
                            :category="$product->name"
                            :included="$item['profile']['included']"
                            :not-included="$item['profile']['notIncluded']"
                            action="See details"
                            :action-href="$item['offering']->path()"
                        />
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($others !== [])
            <section aria-labelledby="related-heading" class="flex flex-col gap-6">
                <x-ui.section-heading eyebrow="Also consider" title="Other workspace types" id="related-heading" />
                <ul class="grid gap-4 sm:grid-cols-3">
                    @foreach (array_slice($others, 0, 3) as $other)
                        <li><x-card.workspace :name="$other['name']" :icon="$other['icon'] ?? 'squares-2x2'" :href="$other['path']" class="h-full">{{ $other['summary'] }}</x-card.workspace></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts::site>
