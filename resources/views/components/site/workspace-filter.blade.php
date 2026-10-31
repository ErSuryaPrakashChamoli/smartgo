@props([
    'types',
    'current' => null,
    'path',
])

{{-- Narrows a list of locations to one workspace type with plain links that work without JavaScript (FR-DISC-2). --}}
<nav aria-label="Workspace types" class="flex flex-col gap-3">
    <p class="text-sm font-semibold text-forest">Show locations offering</p>
    <ul class="flex flex-wrap gap-2">
        <li>
            <a href="{{ $path }}" aria-current="{{ $current === null ? 'page' : 'false' }}"
                @class(['inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold ring-1 ring-inset', 'bg-forest text-paper ring-forest' => $current === null, 'bg-surface text-forest ring-control hover:bg-mist' => $current !== null])>All workspace types</a>
        </li>
        @foreach ($types as $type)
            <li>
                <a href="{{ $path }}?workspace={{ $type->slug }}" aria-current="{{ $current?->is($type) ? 'page' : 'false' }}"
                    @class(['inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold ring-1 ring-inset', 'bg-forest text-paper ring-forest' => $current?->is($type), 'bg-surface text-forest ring-control hover:bg-mist' => ! $current?->is($type)])>{{ $type->name }}</a>
            </li>
        @endforeach
    </ul>
</nav>
