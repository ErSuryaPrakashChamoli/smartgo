@props([
    'segments' => [],
])

{{-- The customer segments SmartGro serves (OD-04), from records; a segment links only to its published solutions page. --}}
<section class="py-14 sm:py-20" aria-labelledby="segments-heading">
    <div class="container-page flex flex-col gap-6 sm:gap-8">
        <div class="flex max-w-2xl flex-col gap-2" data-reveal>
            <h2 id="segments-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Who we work with</h2>
            <p class="text-lg text-pretty text-muted">Flexible workspaces for every kind of team, from one person to a whole company.</p>
        </div>

        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group>
            @foreach ($segments as $segment)
                <li>
                    @if ($segment['href'])
                        <a href="{{ $segment['href'] }}" class="group flex h-full min-h-16 items-center justify-between gap-3 rounded-2xl bg-surface px-5 py-4 font-semibold text-forest shadow-card ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:text-leaf-700 hover:shadow-raised">
                            {{ $segment['name'] }}
                            <x-heroicon-m-arrow-right class="size-4 shrink-0 text-leaf-700 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                        </a>
                    @else
                        <p class="flex h-full min-h-16 items-center rounded-2xl bg-surface px-5 py-4 font-semibold text-forest ring-1 ring-line">{{ $segment['name'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
