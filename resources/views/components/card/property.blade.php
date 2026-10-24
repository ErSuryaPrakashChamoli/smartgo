@props([
    'name',
    'area',
    'city',
    'href' => '#',
    'workspaces' => [],
    'availability' => 'on-request',
    'price' => null,
    'priceUnit' => null,
    'photo' => null,
])

<article {{ $attributes->class(['group relative flex flex-col overflow-hidden rounded-2xl bg-surface shadow-card ring-1 ring-line transition-shadow duration-200 hover:shadow-raised']) }}>
    <x-ui.photo :src="$photo" :alt="$name" label="Photo to be supplied" class="aspect-[4/3] w-full" />
    <div class="flex flex-1 flex-col gap-4 p-5">
        <div class="flex flex-col gap-1">
            <p class="flex items-center gap-1.5 text-sm text-muted">
                <x-heroicon-m-map-pin class="size-4 text-leaf-700" aria-hidden="true" />
                {{ $area }}, {{ $city }}
            </p>
            <h3 class="font-display text-subheading font-semibold text-forest">
                <a href="{{ $href }}" class="rounded-sm after:absolute after:inset-0">{{ $name }}</a>
            </h3>
        </div>
        @if ($workspaces !== [])
            <ul class="flex flex-wrap gap-1.5" aria-label="Workspace types">
                @foreach ($workspaces as $workspace)
                    <li class="rounded-md bg-paper px-2 py-1 text-xs font-medium text-ink ring-1 ring-line">{{ $workspace }}</li>
                @endforeach
            </ul>
        @endif
        <div class="mt-auto flex flex-wrap items-end justify-between gap-3 border-t border-line pt-4">
            <x-ui.price :amount="$price" :unit="$priceUnit" />
            <x-ui.availability :status="$availability" />
        </div>
    </div>
</article>
