@props([
    'items',
    'sample' => false,
])

@php
    // Each moving copy holds the list often enough to be wider than a large screen, so the loop never shows a gap.
    $repeat = max(1, (int) ceil(12 / max(1, count($items))));
    $loopItems = array_merge(...array_fill(0, $repeat, $items));
    $length = count($loopItems);
@endphp

{{--
    The highlights strip under the home page's banners (OD-169): the published highlights (or, until there are any,
    facts from the records), moving slowly from left to right. Example text says so in a fixed label at the start. The
    pointer or keyboard focus holds it and the pause button stops it (WCAG 2.2.2); under reduced motion it stands still
    and scrolls sideways instead. Screen readers get the list once; the moving copies are hidden from them.
--}}
<section class="relative overflow-hidden bg-forest text-paper" aria-labelledby="highlights-heading" data-surface="dark" data-marquee>
    <h2 id="highlights-heading" class="sr-only">{{ $sample ? 'Example highlights' : 'Highlights' }}</h2>
    <ul class="sr-only">
        @foreach ($items as $item)
            <li>{{ $item['text'] }}</li>
        @endforeach
    </ul>

    <div class="flex items-center">
        @if ($sample)
            <p class="relative z-10 flex shrink-0 items-center self-stretch bg-forest-soft pr-4 pl-5 sm:pl-8" aria-hidden="true">
                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide text-paper uppercase ring-1 ring-white/25">Example highlights</span>
            </p>
        @endif

        <div class="marquee-viewport relative min-w-0 flex-1 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_5%,black_95%,transparent)]" aria-hidden="true">
            <div @class([
                'marquee-track flex w-max',
                '[--marquee-duration:45s]' => $length <= 14,
                '[--marquee-duration:60s]' => $length > 14 && $length <= 20,
                '[--marquee-duration:80s]' => $length > 20,
            ])>
                @foreach ([false, true] as $isCopy)
                    <ul @class(['flex shrink-0 items-center gap-10 py-4 pr-10 sm:py-5', 'marquee-copy' => $isCopy])>
                        @foreach ($loopItems as $item)
                            <li class="flex items-center gap-3 text-sm font-medium whitespace-nowrap sm:text-[0.9375rem]">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-leaf-300 ring-1 ring-white/10">
                                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="size-4" />
                                </span>
                                {{ $item['text'] }}
                                <span class="ml-7 size-1.5 rounded-full bg-leaf-300/60"></span>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>

        {{-- Shown by the script, which makes it work. --}}
        <button
            type="button"
            class="relative z-10 mr-3 ml-1 flex size-9 shrink-0 items-center justify-center rounded-full text-paper/80 ring-1 ring-white/20 transition-colors hover:bg-white/10 hover:text-paper sm:mr-6"
            aria-label="Pause the highlights"
            data-marquee-toggle
            hidden
        >
            <x-heroicon-m-pause class="size-4" aria-hidden="true" data-marquee-pause-icon />
            <x-heroicon-m-play class="hidden size-4" aria-hidden="true" data-marquee-play-icon />
        </button>
    </div>
</section>
