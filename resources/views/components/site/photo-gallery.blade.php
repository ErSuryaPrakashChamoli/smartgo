@props([
    'photos',
    'sizes' => '(min-width: 1024px) 700px, 100vw',
    'rounded' => 'rounded-2xl',
])

{{--
    A page's main photo with every photo as a small one under it (location, workspace and space pages, OD-183). Each
    photo comes from WorkspaceCatalogue or SpaceListingPages, so it has already been checked; a stock photo carries its
    label (OD-176). Choosing a small photo shows it in the main frame, in place (never full screen), with its label and
    credit, and marks it as the current one (app.js, [data-photo-gallery]); a page's other photos can show themselves
    here too ([data-photo-show]).
--}}
@php
    $main = $photos[0];
    $count = count($photos);
@endphp
<div {{ $attributes->class('flex min-w-0 flex-col gap-3') }} data-photo-gallery>
    <figure class="flex flex-col gap-2">
        <div class="relative">
            <img src="{{ $main['src'] }}" srcset="{{ $main['srcset'] }}" sizes="{{ $sizes }}" alt="{{ $main['alt'] }}"
                @if ($main['dimensions']) width="{{ $main['dimensions']['width'] }}" height="{{ $main['dimensions']['height'] }}" @endif
                decoding="async" @class(['aspect-[4/3] h-auto w-full bg-mist object-cover', $rounded]) data-photo-main>
            <x-site.photo-label :label="$main['label'] ?? null" always data-photo-label />
        </div>
        <figcaption class="text-sm text-muted empty:hidden" data-photo-credit>{{ $main['credit'] }}</figcaption>
    </figure>

    @if ($count > 1)
        <ul class="-m-1 flex snap-x gap-3 overflow-x-auto p-1" aria-label="All {{ $count }} photos: choose one to show it above">
            @foreach ($photos as $photo)
                <li class="w-[calc((100%-2.25rem)/4)] shrink-0 snap-start">
                    <button type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                        class="group relative block w-full cursor-pointer overflow-hidden rounded-xl"
                        data-photo-thumb
                        data-src="{{ $photo['src'] }}" data-srcset="{{ $photo['srcset'] }}" data-alt="{{ $photo['alt'] }}"
                        data-width="{{ $photo['dimensions']['width'] ?? '' }}" data-height="{{ $photo['dimensions']['height'] ?? '' }}"
                        data-label="{{ $photo['label'] ?? '' }}" data-credit="{{ $photo['credit'] ?? '' }}">
                        <img src="{{ $photo['src'] }}" srcset="{{ $photo['srcset'] }}" sizes="(min-width: 1024px) 170px, 25vw" alt=""
                            @if ($photo['dimensions']) width="{{ $photo['dimensions']['width'] }}" height="{{ $photo['dimensions']['height'] }}" @endif
                            loading="lazy" decoding="async" class="aspect-square h-auto w-full bg-mist object-cover opacity-75 transition-opacity group-hover:opacity-100 group-aria-pressed:opacity-100">
                        <span class="pointer-events-none absolute inset-0 rounded-xl ring-3 ring-transparent ring-inset group-aria-pressed:ring-leaf-700" aria-hidden="true"></span>
                        <span class="sr-only">Show photo {{ $loop->iteration }} of {{ $count }}{{ filled($photo['alt']) ? ': '.$photo['alt'] : '' }}</span>
                        @if (filled($photo['label'] ?? null))
                            <span class="hidden sm:contents"><x-site.photo-label label="Stock photo" compact /></span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
