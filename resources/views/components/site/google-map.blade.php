@props([
    'address',
])

@php
    $place = trim(preg_replace('/\s+/', ' ', $address));
    $query = rawurlencode($place);
@endphp

{{--
    A Google map of a published address that loads only when the visitor asks for it (OD-180): Google is not
    contacted, and sets no cookies, before "Show map" is pressed on this device. The button and its note appear with
    the site's script (resources/js/app.js), which puts the map in the placeholder's place and remembers the choice
    in browser storage; without it the placeholder keeps a link to the address in Google Maps. The caller sizes it. The public content security policy allows framing
    Google Maps and nothing else.
--}}
<div {{ $attributes->class(['relative isolate overflow-hidden bg-forest']) }} data-map data-map-src="https://www.google.com/maps?q={{ $query }}&amp;output=embed" data-map-title="Map of {{ $place }}">
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(to_right,rgb(255_255_255/0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.05)_1px,transparent_1px)] bg-[size:2.5rem_2.5rem]" aria-hidden="true"></div>
    <div class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center" data-map-frame>
        <span class="flex size-12 items-center justify-center rounded-full bg-white/10 text-leaf-300">
            <x-heroicon-o-map class="size-6" aria-hidden="true" />
        </span>
        <button type="button" class="rounded-full bg-paper px-4 py-2 text-sm font-semibold text-forest hover:bg-white" data-map-load hidden>Show map</button>
        <p class="max-w-xs text-xs text-paper/75" data-map-note hidden>Loads Google Maps, which may set cookies. This device will remember your choice.</p>
        <a href="https://www.google.com/maps/search/?api=1&amp;query={{ $query }}" rel="noopener" class="inline-flex items-center gap-1 text-xs text-paper/80 hover:text-leaf-300">
            <x-heroicon-m-arrow-top-right-on-square class="size-3.5 shrink-0" aria-hidden="true" />
            Open in Google Maps
        </a>
    </div>
</div>
