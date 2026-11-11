@props([
    'categories',
    'properties',
])

{{--
    The workspace finder, as a search bar under the hero (OD-144, OD-146): a plain form that needs no JavaScript
    (WorkspaceFinderController), offering only the active workspace types and the locations on the website.
    Team size and move-in date are left out: they need verified inventory, which does not exist yet
    (OD-113 d). Prices and availability are never shown.
--}}
<div {{ $attributes->class('flex flex-col gap-3') }}>
    <form id="find" method="get" action="{{ route('workspaces.find', [], false) }}" role="search" aria-label="Find a workspace"
        class="flex scroll-mt-48 flex-col gap-1 rounded-[1.75rem] bg-surface p-2 text-ink shadow-card ring-1 ring-line sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <label for="finder-workspace" class="pointer-events-none absolute top-2.5 left-5 text-xs font-semibold text-muted">Workspace</label>
            <select id="finder-workspace" name="workspace" class="h-16 w-full cursor-pointer appearance-none rounded-3xl border-0 bg-transparent pt-5 pr-11 pl-5 text-base font-semibold text-forest hover:bg-mist focus:bg-mist">
                <option value="">Any workspace type</option>
                @foreach ($categories as $category)
                    <option value="{{ $category['slug'] }}">{{ $category['name'] }}</option>
                @endforeach
            </select>
            <x-heroicon-m-chevron-down class="pointer-events-none absolute top-1/2 right-4 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
        </div>
        @if ($properties->isNotEmpty())
            <span class="mx-4 h-px bg-line sm:mx-0 sm:h-10 sm:w-px" aria-hidden="true"></span>
            <div class="relative flex-1">
                <label for="finder-location" class="pointer-events-none absolute top-2.5 left-5 text-xs font-semibold text-muted">Location</label>
                <select id="finder-location" name="location" class="h-16 w-full cursor-pointer appearance-none rounded-3xl border-0 bg-transparent pt-5 pr-11 pl-5 text-base font-semibold text-forest hover:bg-mist focus:bg-mist">
                    <option value="">Any location</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->getKey() }}">SmartGro {{ $property->name }} · {{ collect([$property->microMarket?->name, $property->city->name])->filter()->join(', ') }}</option>
                    @endforeach
                </select>
                <x-heroicon-m-chevron-down class="pointer-events-none absolute top-1/2 right-4 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
            </div>
        @endif
        <x-ui.button type="submit" size="lg" pill icon="magnifying-glass" class="sm:ml-1">Search</x-ui.button>
    </form>
    <p class="flex items-start gap-2 text-sm text-muted">
        <x-heroicon-m-information-circle class="mt-0.5 size-4 shrink-0 text-leaf-700" aria-hidden="true" />
        Prices and availability are not shown online yet. SmartGro shares them when you get in touch.
    </p>
</div>
