{{-- A listed space's own page (OD-177): its type, location, summary, details and features as the back office wrote them, and ways to ask about it. An example space says so and is never indexed; a staff preview shows a version as it will appear. No price or availability is shown. --}}
<x-layouts::site
    :title="$space['name'].' at '.$space['location']['name']"
    :description="$space['summary']"
    :canonical="$canonical ?? null"
    :noindex="$space['sample'] || $preview !== null"
    :navigation="$navigation"
    :mobile-actions="$space['enquiryHref'] ? [['label' => 'Enquire', 'href' => $space['enquiryHref'], 'external' => false]] : null"
>
    @if ($preview !== null)
        <x-slot:banner>
            <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
        </x-slot:banner>
    @endif

    @if ($space['sample'])
        <x-site.sample-content-notice />
    @endif

    <section class="border-b border-line bg-surface">
        <div class="container-page grid items-center gap-8 py-10 lg:grid-cols-12 lg:gap-12 lg:py-14">
            <div @class(['flex flex-col gap-5', 'lg:col-span-7' => $space['photo'] !== null, 'lg:col-span-12' => $space['photo'] === null])>
                <x-site.breadcrumb :items="array_values(array_filter([
                    $space['location']['path'] ? ['label' => $space['location']['name'], 'href' => $space['location']['path']] : ['label' => $space['location']['name']],
                    ['label' => $space['name']],
                ]))" />
                <div class="flex flex-wrap items-center gap-2">
                    @if ($space['type'])
                        <x-ui.badge tone="brand">{{ $space['type'] }}</x-ui.badge>
                    @endif
                    @if ($space['sample'])
                        <x-ui.badge tone="warning">Example space</x-ui.badge>
                    @endif
                </div>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">{{ $space['name'] }}</h1>
                <p class="flex items-center gap-2 text-ink">
                    <x-heroicon-o-map-pin class="size-5 shrink-0 text-leaf-700" aria-hidden="true" />
                    @if ($space['location']['path'])
                        <a href="{{ $space['location']['path'] }}" class="font-semibold text-forest underline-offset-4 hover:underline">{{ $space['location']['name'] }}</a>
                    @else
                        <span class="font-semibold text-forest">{{ $space['location']['name'] }}</span>
                    @endif
                    @if ($space['location']['area'] !== '')
                        <span class="text-muted">· {{ $space['location']['area'] }}</span>
                    @endif
                </p>
                @if ($space['summary'])
                    <p class="max-w-2xl text-lead text-muted">{{ $space['summary'] }}</p>
                @endif
                @if ($space['enquiryHref'] || $space['tourHref'])
                    <div class="flex flex-col gap-3 sm:flex-row">
                        @if ($space['enquiryHref'])
                            <x-ui.button size="lg" :href="$space['enquiryHref']" icon="chat-bubble-left-right">Ask about this space</x-ui.button>
                        @endif
                        @if ($space['tourHref'])
                            <x-ui.button size="lg" variant="outline" :href="$space['tourHref']" icon="calendar-days">Book a tour</x-ui.button>
                        @endif
                    </div>
                    <p class="text-sm text-muted">An enquiry or tour request does not reserve anything. Prices and availability are confirmed in writing.</p>
                @endif
            </div>
            @if ($space['photo'] !== null)
                {{-- The space's photo (OD-177 e); a stock photo says it is not the location (OD-176). --}}
                <figure class="flex flex-col gap-2 lg:col-span-5">
                    <div class="relative">
                        <img src="{{ $space['photo']['src'] }}" srcset="{{ $space['photo']['srcset'] }}" sizes="(min-width: 1024px) 500px, 100vw" alt="{{ $space['photo']['alt'] }}"
                            @if ($space['photo']['dimensions']) width="{{ $space['photo']['dimensions']['width'] }}" height="{{ $space['photo']['dimensions']['height'] }}" @endif
                            decoding="async" class="aspect-[4/3] h-auto w-full rounded-3xl bg-mist object-cover">
                        <x-site.photo-label :label="$space['photo']['label']" />
                    </div>
                    @if (filled($space['photo']['credit']))
                        <figcaption class="text-sm text-muted">{{ $space['photo']['credit'] }}</figcaption>
                    @endif
                </figure>
            @endif
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        @if ($space['paragraphs'] !== [] || $space['features'] !== [] || $space['seats'])
            <section aria-labelledby="about-heading" class="grid gap-10 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <div class="flex flex-col gap-4">
                    <h2 id="about-heading" class="font-display text-heading font-semibold text-forest">About this space</h2>
                    @foreach ($space['paragraphs'] as $paragraph)
                        <p class="max-w-prose text-ink">{{ $paragraph }}</p>
                    @endforeach
                </div>
                @if ($space['features'] !== [] || $space['seats'])
                    <div class="flex flex-col gap-4 rounded-3xl bg-surface p-6 ring-1 ring-line">
                        <h2 class="font-display text-subheading font-semibold text-forest">At a glance</h2>
                        <ul class="flex flex-col gap-3">
                            @if ($space['seats'])
                                <li class="flex items-start gap-3 text-ink">
                                    <x-heroicon-o-user-group class="mt-0.5 size-5 shrink-0 text-leaf-700" aria-hidden="true" />
                                    {{ $space['seats'] }} {{ $space['seats'] === 1 ? 'seat' : 'seats' }}
                                </li>
                            @endif
                            @foreach ($space['features'] as $feature)
                                <li class="flex items-start gap-3 text-ink">
                                    <x-heroicon-o-check-circle class="mt-0.5 size-5 shrink-0 text-leaf-700" aria-hidden="true" />
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        @if ($space['others'] !== [])
            <section aria-labelledby="others-heading" class="flex flex-col gap-6">
                <x-ui.section-heading :eyebrow="$space['type'] ?? 'More like this'" title="Other spaces like this" id="others-heading" />
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($space['others'] as $other)
                        <li>
                            <a href="{{ $other['href'] }}" class="group flex h-full items-center justify-between gap-3 rounded-2xl bg-surface p-4 ring-1 ring-line transition hover:ring-leaf-300">
                                <span class="flex flex-col">
                                    <span class="font-semibold text-forest">{{ $other['name'] }}</span>
                                    <span class="text-sm text-muted">{{ $other['location'] }}</span>
                                </span>
                                <x-heroicon-m-arrow-right class="size-4 shrink-0 text-leaf-700 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-site.cta-band eyebrow="Interested?" title="Tell SmartGro what you need">
            SmartGro will reply with the details for this space and the options around it. An enquiry does not reserve anything.
            <x-slot:actions>
                @if ($space['enquiryHref'])
                    <x-ui.button size="lg" variant="inverse" :href="$space['enquiryHref']" icon="chat-bubble-left-right">Send an enquiry</x-ui.button>
                @else
                    <x-ui.button size="lg" variant="inverse" href="/#contact" icon="chat-bubble-left-right">Contact SmartGro</x-ui.button>
                @endif
            </x-slot:actions>
        </x-site.cta-band>
    </div>
</x-layouts::site>
