@props([
    'property',
    'photo' => null,
])

@php($typeCount = $property->publishedOfferings->count())

{{-- One location: its published main photo (real photography of this property, BR-MED-2, or a stock photo published as sample content and labelled so, OD-176) or branded artwork, never another image. --}}
<article class="group relative isolate flex aspect-[4/3] flex-col justify-end overflow-hidden rounded-3xl bg-forest text-paper shadow-card transition duration-300 hover:-translate-y-1 hover:shadow-raised sm:aspect-[5/4]" data-surface="dark">
    @if ($photo)
        <img
            src="{{ $photo['src'] }}"
            srcset="{{ $photo['srcset'] }}"
            sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"
            alt="{{ $photo['alt'] }}"
            loading="lazy"
            decoding="async"
            class="absolute inset-0 -z-20 size-full object-cover transition-transform duration-500 motion-safe:group-hover:scale-105"
        >
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-forest via-forest/70 to-forest/0" aria-hidden="true"></div>
        <x-site.photo-label :label="$photo['label'] ?? null" top />
    @else
        <x-home.location-art :code="$property->code" class="absolute inset-0 -z-10 size-full transition-transform duration-700 ease-out group-hover:scale-105" />
    @endif

    <div class="flex flex-col gap-2 p-5 sm:p-6">
        @if ($property->openingLabel())
            <span class="inline-flex items-center gap-1 self-start rounded-full bg-paper px-2.5 py-1 text-xs font-semibold text-forest">
                <x-heroicon-m-clock class="size-3.5 text-leaf-700" aria-hidden="true" />
                {{ $property->openingLabel() }}
            </span>
        @endif
        <h3 class="font-editorial text-2xl font-semibold">
            <a href="{{ $property->path() }}" class="rounded-sm after:absolute after:inset-0">SmartGro {{ $property->name }}</a>
        </h3>
        <p class="flex items-start gap-1.5 text-sm text-paper/85">
            <x-heroicon-m-map-pin class="mt-0.5 size-4 shrink-0 text-leaf-300" aria-hidden="true" />
            {{ $property->addressLine() }}
        </p>
        <div class="mt-2 flex items-center justify-between gap-3 border-t border-white/15 pt-3">
            <p class="text-sm font-medium text-leaf-300">
                {{ $typeCount > 0 ? $typeCount.' '.str('workspace type')->plural($typeCount) : 'Ask about workspaces' }}
            </p>
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full ring-1 ring-paper/50 transition-colors group-hover:bg-paper group-hover:text-forest" aria-hidden="true">
                <x-heroicon-m-arrow-right class="size-5" />
            </span>
        </div>
    </div>
</article>
