@props([
    'slides',
    'isPreview' => false,
])

@php
    $count = count($slides);
@endphp

{{--
    The home page's banner carousel (OD-169): the published banners, by position, in the calm split layout of the
    owner's design (OD-146), lower than the single hero it replaces. Lower again since OD-175 (less padding, the heading
    capped at 50 px from 1280 px wide, where a two-line banner heading then stays on two lines), so the search under the
    highlights strip shows on the first screen of a laptop. Each slide has its heading, line of text and
    buttons on the left and its image filling the right side, fading into the page; an image that is not a photo of
    a SmartGro location says so. The first slide's heading is the page's main heading (not in a back-office preview).

    Without JavaScript the slides are a row that scrolls sideways (with snapping), so every banner can be reached. With
    it (resources/js/app.js), the next banner slides in from the right every few seconds; the pointer or keyboard focus
    on the banners holds it, the pause button stops it, and under reduced motion it never moves by itself. Only the
    banner on screen can be reached by Tab.
--}}
<section
    class="relative isolate overflow-hidden"
    aria-roledescription="carousel"
    aria-label="SmartGro banners"
    data-carousel
    @if ($count > 1) data-carousel-autoplay @endif
>
    <div
        id="home-banners-track"
        class="flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        data-carousel-track
        aria-live="off"
    >
        @foreach ($slides as $slide)
            <div
                class="relative w-full shrink-0 snap-start snap-always"
                role="group"
                aria-roledescription="slide"
                aria-label="{{ $loop->iteration }} of {{ $count }}"
                data-carousel-slide
                @if ($loop->first) data-active @endif
            >
                <div @class([
                    'container-page grid items-center gap-6 pt-6 sm:pt-8 lg:min-h-[22rem] lg:grid-cols-12 lg:pt-8',
                    'pb-20' => $count > 1,
                    'pb-10 lg:pb-8' => $count === 1,
                ])>
                    <div class="relative z-10 flex flex-col gap-5 lg:col-span-6">
                        @if ($loop->first && ! $isPreview)
                            <h1 id="home-heading" class="max-w-xl font-editorial text-display-xl font-semibold text-balance text-forest xl:text-[3.125rem]" data-carousel-rise>{{ $slide['heading'] }}</h1>
                        @else
                            <h2 class="max-w-xl font-editorial text-display-xl font-semibold text-balance text-forest xl:text-[3.125rem]" data-carousel-rise>{{ $slide['heading'] }}</h2>
                        @endif

                        @if (filled($slide['text']))
                            <p class="max-w-xl text-lead text-pretty text-muted" data-carousel-rise>{{ $slide['text'] }}</p>
                        @endif

                        @if ($slide['links'] !== [])
                            <div class="flex flex-col gap-3 pt-1 sm:flex-row" data-carousel-rise>
                                @foreach ($slide['links'] as $link)
                                    <x-ui.button
                                        size="lg"
                                        pill
                                        :variant="$loop->first ? 'primary' : 'outline'"
                                        :href="$link['href']"
                                        :icon="$link['external'] ? 'arrow-top-right-on-square' : ($loop->first ? 'arrow-right' : null)"
                                        :target="$link['external'] ? '_blank' : null"
                                        :rel="$link['external'] ? 'noopener noreferrer' : null"
                                    >{{ $link['label'] }}</x-ui.button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <figure class="relative order-first aspect-video overflow-hidden rounded-3xl bg-mist sm:aspect-[2/1] lg:absolute lg:inset-y-0 lg:right-0 lg:left-[48%] lg:order-none lg:aspect-auto lg:rounded-none lg:bg-transparent lg:[mask-image:linear-gradient(to_right,transparent,black_20%)]">
                        <img
                            src="{{ $slide['image']['src'] }}"
                            srcset="{{ $slide['image']['srcset'] }}"
                            sizes="(min-width: 1024px) 52vw, 100vw"
                            alt="{{ $slide['image']['alt'] }}"
                            @if ($slide['image']['dimensions']) width="{{ $slide['image']['dimensions']['width'] }}" height="{{ $slide['image']['dimensions']['height'] }}" @endif
                            @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                            class="absolute inset-0 size-full object-cover"
                            data-carousel-image
                        >
                        @if ($slide['image']['label'])
                            <figcaption class="absolute right-4 bottom-4 inline-flex max-w-[calc(100%-2rem)] items-center gap-1.5 rounded-full bg-forest/85 px-3 py-1.5 text-xs font-medium text-paper ring-1 ring-white/15 backdrop-blur">
                                <x-heroicon-m-information-circle class="size-3.5 shrink-0 text-leaf-300" aria-hidden="true" />
                                {{ $slide['image']['label'] }}
                            </figcaption>
                        @endif
                    </figure>
                </div>
            </div>
        @endforeach
    </div>

    @if ($count > 1)
        {{-- Shown by the script, which makes them work. --}}
        <div class="pointer-events-none absolute inset-x-0 bottom-5 z-20" data-carousel-controls hidden>
            <div class="container-page flex items-center gap-2">
                <div class="pointer-events-auto flex items-center gap-2 rounded-full bg-surface/90 p-1.5 shadow-card ring-1 ring-line backdrop-blur">
                    <button type="button" class="flex size-9 items-center justify-center rounded-full text-forest transition-colors hover:bg-mist" aria-controls="home-banners-track" aria-label="Previous banner" data-carousel-previous>
                        <x-heroicon-m-chevron-left class="size-5" aria-hidden="true" />
                    </button>
                    <div class="flex items-center gap-1.5 px-1">
                        @foreach ($slides as $slide)
                            <button
                                type="button"
                                class="group relative flex h-9 items-center"
                                aria-controls="home-banners-track"
                                aria-label="Show banner {{ $loop->iteration }} of {{ $count }}"
                                @if ($loop->first) aria-current="true" @endif
                                data-carousel-dot
                            >
                                <span class="carousel-dot relative block h-2 w-2 overflow-hidden rounded-full bg-control/45 transition-[width,background-color] duration-300 group-hover:bg-control group-aria-[current=true]:w-9 group-aria-[current=true]:bg-leaf-700/25">
                                    <span class="carousel-dot-progress absolute inset-y-0 left-0 w-full origin-left scale-x-0 rounded-full bg-leaf-700"></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <button type="button" class="flex size-9 items-center justify-center rounded-full text-forest transition-colors hover:bg-mist" aria-controls="home-banners-track" aria-label="Next banner" data-carousel-next>
                        <x-heroicon-m-chevron-right class="size-5" aria-hidden="true" />
                    </button>
                    <span class="mx-0.5 h-5 w-px bg-line" aria-hidden="true"></span>
                    <button type="button" class="flex size-9 items-center justify-center rounded-full text-forest transition-colors hover:bg-mist" aria-controls="home-banners-track" aria-label="Pause the banners" data-carousel-toggle>
                        <x-heroicon-m-pause class="size-5" aria-hidden="true" data-carousel-pause-icon />
                        <x-heroicon-m-play class="hidden size-5" aria-hidden="true" data-carousel-play-icon />
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
