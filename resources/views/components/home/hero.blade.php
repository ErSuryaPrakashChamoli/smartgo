@props([
    'properties',
    'categories' => [],
    'cityNames' => [],
    'photo' => null,
    'tourOffered' => false,
])

@php
    $where = $cityNames === [] ? null : implode(', ', $cityNames);
    $categoryCount = count($categories);
    $facts = array_values(array_filter([
        $properties->isNotEmpty() ? ['icon' => 'map-pin', 'count' => $properties->count(), 'text' => ' '.str('location')->plural($properties->count()).($where ? ' in '.$where : '')] : null,
        $categoryCount > 0 ? ['icon' => 'squares-2x2', 'count' => $categoryCount, 'text' => ' '.str('workspace type')->plural($categoryCount)] : null,
        ['icon' => 'user-group', 'count' => null, 'text' => 'For individuals to enterprises'],
        $tourOffered ? ['icon' => 'calendar-days', 'count' => null, 'text' => 'Tours on request'] : null,
    ]));
@endphp

{{--
    The first screen, as in the owner's design (OD-146): a calm, light stage with the headline, two next steps
    and the facts from records on the left, and a photograph of a SmartGro location (or, until one is published,
    the owner's AI-generated image, labelled as such) filling the right side and fading into the page. Text rises in on arrival;
    nothing moves under reduced motion. Lower since OD-169; once a banner is published, the carousel replaces it.
--}}
<section class="relative isolate overflow-hidden" aria-labelledby="home-heading">
    <div class="container-page grid items-center gap-8 pt-8 pb-10 sm:pt-10 lg:min-h-[31rem] lg:grid-cols-12 lg:pt-12 lg:pb-12">
        <div class="relative z-10 flex flex-col gap-6 lg:col-span-7">
            <h1 id="home-heading" class="max-w-xl font-editorial text-display-xl font-semibold text-balance text-forest">
                <span class="block motion-safe:animate-rise">A better space</span>
                <span class="block motion-safe:animate-rise motion-safe:[animation-delay:120ms]">to do your</span>
                <span class="block text-leaf-700 motion-safe:animate-rise motion-safe:[animation-delay:240ms]">best work.</span>
            </h1>
            <p class="max-w-xl text-lead text-pretty text-muted motion-safe:animate-rise motion-safe:[animation-delay:360ms]">
                Flexible workspaces{{ $where ? ' in '.$where : '' }} for freelancers, startups and growing businesses. Designed to help people and teams go further.
            </p>
            <div class="flex flex-col gap-3 sm:flex-row motion-safe:animate-rise motion-safe:[animation-delay:480ms]">
                <x-ui.button size="lg" pill :href="$categoryCount > 0 ? '#find' : '#contact'" icon="arrow-right">Find your workspace</x-ui.button>
                @if ($properties->isNotEmpty())
                    <x-ui.button size="lg" pill variant="outline" href="/locations" icon="map-pin">Explore locations</x-ui.button>
                @else
                    <x-ui.button size="lg" pill variant="outline" href="#contact">Talk to SmartGro</x-ui.button>
                @endif
            </div>
            <ul class="grid grid-cols-2 gap-x-5 gap-y-4 pt-3 sm:grid-cols-4 motion-safe:animate-rise motion-safe:[animation-delay:600ms]" aria-label="SmartGro at a glance">
                @foreach ($facts as $fact)
                    <li class="flex items-center gap-3 text-sm/snug text-ink">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-mist text-leaf-700" aria-hidden="true">
                            <x-dynamic-component :component="'heroicon-o-'.$fact['icon']" class="size-5" />
                        </span>
                        <span>@if ($fact['count'] !== null)<span class="tabular-nums" data-count-to="{{ $fact['count'] }}">{{ $fact['count'] }}</span>@endif{{ $fact['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <figure class="relative aspect-[4/3] overflow-hidden rounded-4xl bg-mist motion-safe:animate-fade lg:absolute lg:inset-y-0 lg:right-0 lg:left-[56%] lg:aspect-auto lg:rounded-none lg:bg-transparent lg:[mask-image:linear-gradient(to_right,transparent,black_22%)]">
            @if ($photo)
                <img
                    src="{{ $photo['src'] }}"
                    srcset="{{ $photo['srcset'] }}"
                    sizes="(min-width: 1024px) 54vw, 100vw"
                    alt="{{ $photo['alt'] }}"
                    @if ($photo['dimensions']) width="{{ $photo['dimensions']['width'] }}" height="{{ $photo['dimensions']['height'] }}" @endif
                    fetchpriority="high"
                    class="absolute inset-0 size-full object-cover"
                >
                <figcaption class="absolute right-4 bottom-4 inline-flex max-w-[calc(100%-2rem)] items-center gap-1.5 rounded-full bg-forest/80 px-3 py-1.5 text-xs font-medium text-paper backdrop-blur">
                    @if ($photo['label'] ?? null)
                        <x-heroicon-m-information-circle class="size-3.5 shrink-0 text-leaf-300" aria-hidden="true" />
                    @else
                        <x-heroicon-m-map-pin class="size-3.5 shrink-0 text-leaf-300" aria-hidden="true" />
                    @endif
                    {{ $photo['caption'] }}
                </figcaption>
            @else
                {{-- The owner's AI-generated image (OD-148), always labelled; a published photograph replaces it. --}}
                <img
                    src="/images/home/coworking-interior-1024.webp"
                    srcset="/images/home/coworking-interior-640.webp 640w, /images/home/coworking-interior-1024.webp 1024w, /images/home/coworking-interior-1774.webp 1774w"
                    sizes="(min-width: 1024px) 1280px, 150vw"
                    width="1774"
                    height="887"
                    alt="A bright coworking space with large windows, plants, a green sofa and a long wooden table"
                    fetchpriority="high"
                    class="absolute inset-0 size-full object-cover object-[60%_50%] lg:object-[72%_50%]"
                >
                <figcaption class="absolute right-4 bottom-4 inline-flex max-w-[calc(100%-2rem)] items-center gap-1.5 rounded-full bg-forest/85 px-3 py-1.5 text-xs font-medium text-paper ring-1 ring-white/15 backdrop-blur">
                    <x-heroicon-m-sparkles class="size-3.5 shrink-0 text-leaf-300" aria-hidden="true" />
                    AI-generated image, not a photo of a SmartGro location
                </figcaption>
            @endif
        </figure>
    </div>
</section>
