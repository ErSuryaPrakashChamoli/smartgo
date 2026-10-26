@props(['data'])

@if ($data['image'])
    @php($image = $data['image'])
    <figure class="container-page flex flex-col gap-3 py-10 sm:py-14">
        <div class="relative">
            <img
                src="{{ $image['src'] }}"
                srcset="{{ $image['srcset'] }}"
                sizes="(min-width: 1280px) 1200px, 100vw"
                alt="{{ $image['alt'] }}"
                @if ($image['dimensions']) width="{{ $image['dimensions']['width'] }}" height="{{ $image['dimensions']['height'] }}" @endif
                loading="lazy"
                decoding="async"
                class="h-auto w-full rounded-3xl bg-mist object-cover"
            >
            @if ($image['isConcept'])
                {{-- BR-MED-2: AI concepts and renders are never presented as real photography. --}}
                <span class="absolute top-3 left-3 rounded-full bg-forest/90 px-3 py-1 text-xs font-semibold text-paper">Concept visual</span>
            @endif
        </div>
        @if (filled($image['caption']) || filled($image['credit']))
            <figcaption class="flex flex-wrap gap-x-3 text-sm text-muted">
                @if (filled($image['caption']))
                    <span>{{ $image['caption'] }}</span>
                @endif
                @if (filled($image['credit']))
                    <span>{{ $image['credit'] }}</span>
                @endif
            </figcaption>
        @endif
    </figure>
@endif
