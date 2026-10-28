@props(['property'])

{{-- A property on the website: its name, address, opening label and the workspace offerings published for it. --}}
<article {{ $attributes->class(['relative flex flex-col gap-4 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line transition-shadow duration-200 hover:shadow-raised']) }}>
    <div class="flex items-start gap-4">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-mist text-leaf-700" aria-hidden="true">
            <x-heroicon-o-building-office-2 class="size-6" />
        </span>
        <div class="flex flex-col gap-1">
            <h3 class="font-display text-subheading font-semibold text-forest">
                <a href="{{ $property->path() }}" class="rounded-sm after:absolute after:inset-0 hover:text-leaf-700">SmartGro {{ $property->name }}</a>
            </h3>
            <p class="flex items-center gap-1.5 text-sm text-muted">
                <x-heroicon-m-map-pin class="size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                {{ $property->addressLine() }}
            </p>
            @if ($property->openingLabel())
                <x-ui.badge tone="info" icon="clock" class="mt-1 self-start">{{ $property->openingLabel() }}</x-ui.badge>
            @endif
        </div>
    </div>
    @if ($property->publishedOfferings->isNotEmpty())
        <ul class="flex flex-wrap gap-1.5" aria-label="Workspace types">
            @foreach ($property->publishedOfferings->sortBy(fn ($offering) => $offering->product->sort_order) as $offering)
                <li class="rounded-md bg-paper px-2 py-1 text-xs font-medium text-ink ring-1 ring-line">{{ $offering->product->name }}</li>
            @endforeach
        </ul>
    @endif
    <span class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-leaf-700">
        View location <x-heroicon-m-arrow-right class="size-4" aria-hidden="true" />
    </span>
</article>
