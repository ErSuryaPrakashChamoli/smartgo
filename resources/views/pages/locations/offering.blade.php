{{-- One published workspace offering at a property (IA §3.1 item 3), or a staff preview of an offering profile version. No capacity, availability or price is shown: they are confirmed on request. --}}
@php
    $photos = $profile['photos'] ?? [];
    $title = $offering->product->name.' at SmartGro '.$property->name;
@endphp
<x-layouts::site
    :title="$title.', '.$property->city->name"
    :meta-title="$profile['seoTitle'] ?? null"
    :description="$profile['seoDescription'] ?? $profile['headline'] ?? $title.'.'"
    :noindex="$preview !== null"
    :canonical="$canonical"
    :navigation="$navigation"
    :mobile-actions="[['label' => 'Enquire', 'href' => $enquiryHref, 'external' => false]]"
    :mobile-contact="$contact['details']"
>
    @if ($preview !== null)
        <x-slot:banner>
            <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
        </x-slot:banner>
    @endif

    <section class="border-b border-line bg-surface">
        <div @class(['container-page grid gap-8 py-8 lg:gap-12 lg:py-12', 'lg:grid-cols-12' => $photos !== []])>
            <div @class(['flex flex-col gap-5', 'lg:col-span-7' => $photos !== [], 'max-w-3xl' => $photos === []])>
                <x-site.breadcrumb :items="[
                    ['label' => 'Locations', 'href' => '/locations'],
                    ['label' => $property->city->name, 'href' => '/locations/'.$property->city->slug],
                    ['label' => 'SmartGro '.$property->name, 'href' => $preview === null ? $property->path() : null],
                    ['label' => $offering->product->name],
                ]" />
                <x-ui.badge tone="brand" icon="squares-2x2" class="self-start">{{ $offering->product->name }}</x-ui.badge>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">{{ $title }}</h1>
                @if (filled($profile['headline'] ?? null))
                    <p class="text-lead text-pretty text-muted">{{ $profile['headline'] }}</p>
                @endif
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.availability status="on-request" />
                    <x-ui.price />
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" :href="$enquiryHref" icon="chat-bubble-left-right">Enquire about this workspace</x-ui.button>
                    @if ($preview === null)
                        <x-ui.button size="lg" variant="secondary" :href="$property->path()">About SmartGro {{ $property->name }}</x-ui.button>
                    @endif
                </div>
                <p class="text-sm text-muted">An enquiry does not reserve anything. SmartGro replies with capacity, prices and availability in writing.</p>
            </div>
            @if ($photos !== [])
                @php($mainPhoto = $photos[0])
                <figure class="flex flex-col gap-2 lg:col-span-5">
                    <img src="{{ $mainPhoto['src'] }}" srcset="{{ $mainPhoto['srcset'] }}" sizes="(min-width: 1024px) 500px, 100vw" alt="{{ $mainPhoto['alt'] }}"
                        @if ($mainPhoto['dimensions']) width="{{ $mainPhoto['dimensions']['width'] }}" height="{{ $mainPhoto['dimensions']['height'] }}" @endif
                        decoding="async" class="aspect-[4/3] h-auto w-full rounded-3xl bg-mist object-cover">
                    @if (filled($mainPhoto['credit']))
                        <figcaption class="text-sm text-muted">{{ $mainPhoto['credit'] }}</figcaption>
                    @endif
                </figure>
            @endif
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        @if (filled($profile['description'] ?? null))
            <p class="max-w-3xl text-lead text-pretty whitespace-pre-line text-ink">{{ $profile['description'] }}</p>
        @endif

        @if ($profile['included'] !== [] || $profile['notIncluded'] !== [])
            <section aria-labelledby="inclusions-heading" class="grid gap-8 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <x-ui.section-heading eyebrow="What to expect" title="Included and not included" id="inclusions-heading" />
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:col-span-8">
                    @if ($profile['included'] !== [])
                        <div class="rounded-2xl bg-surface p-6 ring-1 ring-line">
                            <h3 class="font-semibold text-forest">Included</h3>
                            <ul class="mt-3 flex flex-col gap-2 text-ink">
                                @foreach ($profile['included'] as $item)
                                    <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 size-5 shrink-0 text-available" aria-hidden="true" />{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($profile['notIncluded'] !== [])
                        <div class="rounded-2xl bg-surface p-6 ring-1 ring-line">
                            <h3 class="font-semibold text-forest">Not included</h3>
                            <ul class="mt-3 flex flex-col gap-2 text-ink">
                                @foreach ($profile['notIncluded'] as $item)
                                    <li class="flex gap-2"><x-heroicon-m-minus class="mt-0.5 size-5 shrink-0 text-muted" aria-hidden="true" />{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if (count($photos) > 1)
            <section aria-labelledby="offering-photos-heading" class="flex flex-col gap-6">
                <x-ui.section-heading eyebrow="Photos" title="Photos" id="offering-photos-heading" />
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (array_slice($photos, 1) as $photo)
                        <li>
                            <img src="{{ $photo['src'] }}" srcset="{{ $photo['srcset'] }}" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" alt="{{ $photo['alt'] }}"
                                @if ($photo['dimensions']) width="{{ $photo['dimensions']['width'] }}" height="{{ $photo['dimensions']['height'] }}" @endif
                                loading="lazy" decoding="async" class="aspect-[4/3] h-auto w-full rounded-2xl bg-mist object-cover">
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($otherHere->isNotEmpty())
            <section aria-labelledby="other-here-heading" class="flex flex-col gap-6">
                <x-ui.section-heading eyebrow="Also at this location" :title="'Other workspaces at SmartGro '.$property->name" id="other-here-heading" />
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($otherHere as $other)
                        <li><x-card.workspace :name="$other->product->name" icon="squares-2x2" :href="$other->path()" class="h-full">{{ $other->profile->headline }}</x-card.workspace></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($elsewhere->isNotEmpty())
            <section aria-labelledby="elsewhere-heading" class="flex flex-col gap-6">
                <x-ui.section-heading eyebrow="Elsewhere" :title="$offering->product->name.' at other SmartGro locations'" id="elsewhere-heading" />
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($elsewhere as $other)
                        <li><x-card.workspace :name="'SmartGro '.$other->property->name" icon="building-office-2" :href="$other->path()" class="h-full">{{ $other->property->addressLine() }}</x-card.workspace></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <x-site.contact-section :contact="$contact['details']" :title="$contact['title']" :intro="$contact['intro']" />
</x-layouts::site>
