@props([
    'explore',
    'location',
    'tourHref' => null,
    'enquiryHref' => null,
])

{{--
    A location page's "Explore" section (OD-181, IA §3.1 step 4, BR-3D-5): the 3D walkthrough and the floor plans its
    published profile holds, from LocationExplore. Nothing 3D is fetched until the walkthrough comes near the screen
    (BR-3D-6, OD-184; with the browser set to save data, until the visitor presses "Explore in 3D"): then the model
    and the viewer module (a separate Vite entry) download together, with progress and a way to stop. The heading and notice come from how the model was made and cannot be removed (BR-3D-1, BR-3D-2).
    The guided views are listed as text too, and the floor plans show the same floor without 3D. Without JavaScript
    every floor plan shows, one under the other; with it, the plans become tabs, each shown in place. The script is in
    app.js ([data-twin-walkthrough], [data-floor-plans]).
--}}
@php
    $walkthrough = $explore['walkthrough'];
    $floorPlans = $explore['floorPlans'];
@endphp
<section id="explore" aria-labelledby="explore-heading" class="flex scroll-mt-36 flex-col gap-6">
    <x-ui.section-heading eyebrow="Explore" :title="'Explore '.$location" id="explore-heading">
        {{ match (true) {
            $walkthrough !== null && $floorPlans !== [] => 'Walk through the floor in 3D, or look at the floor plans.',
            $walkthrough !== null => 'Walk through the floor in 3D.',
            default => 'See how the floors are laid out.',
        } }}
    </x-ui.section-heading>

    @if ($walkthrough !== null)
        <div class="flex flex-col gap-4 rounded-2xl bg-surface p-4 shadow-card ring-1 ring-line sm:p-6"
            data-twin-walkthrough
            data-module="{{ $walkthrough['moduleUrl'] }}"
            data-module-imports="{{ implode(' ', $walkthrough['moduleImports']) }}"
            data-decoder-files="{{ implode(' ', $walkthrough['decoderFiles']) }}"
            data-decoders="{{ implode(' ', $walkthrough['decoders']) }}"
            data-model="{{ $walkthrough['url'] }}"
            data-label="{{ $walkthrough['label'] }}">
            <div class="flex flex-col gap-1">
                <h3 class="font-display text-subheading font-semibold text-forest">{{ $walkthrough['heading'] }}</h3>
                <p class="text-sm text-muted">{{ $walkthrough['title'] }}</p>
            </div>

            @if ($walkthrough['notice'])
                <p class="flex items-start gap-2 rounded-lg bg-limited-soft px-3 py-2 text-sm font-semibold text-limited" data-twin-notice>
                    <x-heroicon-m-exclamation-triangle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ $walkthrough['notice'] }}
                </p>
            @endif

            <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl bg-mist ring-1 ring-line sm:aspect-video">
                <div data-twin-stage hidden class="absolute inset-0"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6 text-center" data-twin-poster>
                    <x-heroicon-o-cube-transparent class="size-12 text-leaf-700" aria-hidden="true" />
                    <p class="max-w-md text-pretty text-ink">Turn the floor, zoom in and jump to each area ({{ $walkthrough['size'] }} MB 3D model).</p>
                    <div class="flex flex-wrap justify-center gap-2" data-twin-start hidden>
                        <x-ui.button pill icon="cube-transparent" data-twin-load>Explore in 3D</x-ui.button>
                        <x-ui.button pill variant="secondary" data-twin-stop hidden>Stop loading</x-ui.button>
                    </div>
                    <progress data-twin-progress hidden max="1" aria-label="Download of the 3D model" class="h-2 w-full max-w-xs accent-leaf-700"></progress>
                    <noscript><p class="text-sm text-muted">The 3D walkthrough needs JavaScript. Its views are listed below@if ($floorPlans !== []), and the floor plans show the same layout@endif.</p></noscript>
                </div>
            </div>

            <p role="status" aria-live="polite" class="text-sm text-muted empty:hidden" data-twin-status></p>

            <div data-twin-controls hidden role="group" aria-label="Views of the model" class="flex flex-wrap gap-2">
                <x-ui.button size="sm" pill variant="secondary" icon="arrows-pointing-out" data-twin-whole>Whole floor</x-ui.button>
                @foreach ($walkthrough['stops'] as $stop)
                    <x-ui.button size="sm" pill variant="secondary" data-twin-camera="{{ $stop['node'] }}" class="aria-pressed:bg-forest aria-pressed:text-paper aria-pressed:ring-forest aria-pressed:hover:bg-forest">{{ $stop['title'] }}</x-ui.button>
                @endforeach
            </div>

            <details class="group text-sm text-muted">
                <summary class="cursor-pointer font-semibold text-forest">What the walkthrough shows</summary>
                <ol class="mt-2 list-inside list-decimal">
                    @foreach ($walkthrough['stops'] as $stop)
                        <li>{{ $stop['title'] }}</li>
                    @endforeach
                </ol>
            </details>
        </div>
    @endif

    @if ($floorPlans !== [])
        <div class="flex flex-col gap-4" data-floor-plans>
            <h3 class="font-display text-subheading font-semibold text-forest">Floor plans</h3>

            @if (count($floorPlans) > 1)
                <div role="tablist" aria-label="Floors" class="flex flex-wrap gap-2" data-floor-tabs hidden>
                    @foreach ($floorPlans as $plan)
                        <button type="button" role="tab" id="floor-tab-{{ $loop->index }}" aria-controls="floor-plan-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" @unless ($loop->first) tabindex="-1" @endunless
                            class="h-9 rounded-full px-4 text-sm font-semibold text-forest ring-1 ring-control ring-inset hover:bg-mist aria-selected:bg-forest aria-selected:text-paper aria-selected:ring-forest">{{ $plan['label'] }}</button>
                    @endforeach
                </div>
            @endif

            @foreach ($floorPlans as $plan)
                <figure id="floor-plan-{{ $loop->index }}" class="flex flex-col gap-3" data-floor-plan>
                    <h4 class="font-semibold text-forest" data-floor-plan-heading>{{ $plan['label'] }}</h4>
                    <div class="relative overflow-hidden rounded-xl bg-white ring-1 ring-line">
                        <img src="{{ $plan['src'] }}" srcset="{{ $plan['srcset'] }}" sizes="(min-width: 1280px) 1200px, 100vw" alt="{{ $plan['alt'] }}"
                            @if ($plan['dimensions']) width="{{ $plan['dimensions']['width'] }}" height="{{ $plan['dimensions']['height'] }}" @endif
                            loading="lazy" decoding="async" class="h-auto w-full">
                        @if ($plan['isExample'])
                            <x-site.photo-label :label="\App\Services\Website\LocationExplore::EXAMPLE_FLOOR_PLAN_LABEL" />
                        @endif
                    </div>
                    @if (filled($plan['note']) || filled($plan['credit']))
                        <figcaption class="flex flex-col gap-1 text-sm text-muted">
                            @if (filled($plan['note']))
                                <span class="text-pretty text-ink">{{ $plan['note'] }}</span>
                            @endif
                            @if (filled($plan['credit']))
                                <span>{{ $plan['credit'] }}</span>
                            @endif
                        </figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    @endif

    @if ($tourHref || $enquiryHref)
        <div class="flex flex-col items-start gap-3 rounded-2xl bg-mist p-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-pretty text-forest">The best way to judge a space is to see it. SmartGro shows you round.</p>
            @if ($tourHref)
                <x-ui.button :href="$tourHref" icon="calendar-days">Book a tour</x-ui.button>
            @else
                <x-ui.button :href="$enquiryHref" icon="chat-bubble-left-right">Ask about a visit</x-ui.button>
            @endif
        </div>
    @endif
</section>
