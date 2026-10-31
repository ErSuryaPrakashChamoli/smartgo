@props(['data'])

{{-- The locations on the website (all, or one city's) as cards linking to their pages; left out when there is none to show. --}}
@if ($data['properties']->isNotEmpty())
    <section class="container-page py-10 sm:py-14">
        <div class="flex flex-col gap-8">
            <div class="flex max-w-3xl flex-col gap-3">
                <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
                @if (filled($data['intro']))
                    <p class="text-lead text-pretty text-muted">{{ $data['intro'] }}</p>
                @endif
            </div>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($data['properties'] as $property)
                    <li><x-site.location-card :property="$property" class="h-full" /></li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
