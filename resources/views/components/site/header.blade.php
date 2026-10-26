@props([
    'homeHref' => '/',
    'categories' => [],
    'properties' => [],
    'navigation' => null,
])

{{--
    Menus use the native Popover API: the browser handles opening, closing on Escape or an outside
    click, and the expanded state, so marketing pages ship no framework JavaScript (NFR-PERF-2).
    $navigation (App\Services\Content\SiteNavigation) holds the published menus; without it the
    Phase 2 design previews show their sample menus.
--}}
<header {{ $attributes->class(['sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/85']) }}>
    <div class="container-page flex h-18 items-center justify-between gap-6 lg:h-20">
        <a href="{{ $homeHref }}" class="shrink-0 rounded-md" aria-label="SmartGro home">
            <x-site.brand-logo />
        </a>

        @if ($navigation === null)
            {{-- Phase 2 design preview: sample menus with placeholder links (never served in production). --}}
        <nav aria-label="Main" class="hidden lg:block">
            <ul class="flex items-center gap-1">
                <li>
                    <button
                        type="button"
                        popovertarget="menu-workspaces"
                        class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-[0.9375rem] font-medium text-forest hover:bg-mist [&:has(+:popover-open)]:bg-mist [&:has(+:popover-open)_svg]:rotate-180"
                    >
                        Workspaces
                        <x-heroicon-m-chevron-down class="size-4 transition-transform" aria-hidden="true" />
                    </button>
                    <div
                        id="menu-workspaces"
                        popover
                        class="inset-auto top-22 left-1/2 m-0 w-[min(44rem,calc(100vw-2.5rem))] -translate-x-1/2 rounded-2xl border-0 bg-surface p-3 shadow-raised ring-1 ring-line"
                    >
                        <ul class="grid grid-cols-2 gap-1">
                            @foreach ($categories as $category)
                                <li>
                                    <a href="#" class="flex items-start gap-3 rounded-xl p-3 hover:bg-mist">
                                        <x-dynamic-component :component="'heroicon-o-'.$category['icon']" class="mt-0.5 size-5 shrink-0 text-leaf-700" aria-hidden="true" />
                                        <span class="flex flex-col">
                                            <span class="text-sm font-semibold text-forest">{{ $category['name'] }}</span>
                                            <span class="text-sm text-muted">{{ $category['summary'] }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
                <li>
                    <button
                        type="button"
                        popovertarget="menu-locations"
                        class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-[0.9375rem] font-medium text-forest hover:bg-mist [&:has(+:popover-open)]:bg-mist [&:has(+:popover-open)_svg]:rotate-180"
                    >
                        Locations
                        <x-heroicon-m-chevron-down class="size-4 transition-transform" aria-hidden="true" />
                    </button>
                    <div
                        id="menu-locations"
                        popover
                        class="inset-auto top-22 left-1/2 m-0 w-[min(22rem,calc(100vw-2.5rem))] -translate-x-1/2 rounded-2xl border-0 bg-surface p-4 shadow-raised ring-1 ring-line"
                    >
                        <p class="px-2 text-eyebrow font-semibold text-leaf-700 uppercase">Noida</p>
                        <ul class="mt-2 flex flex-col gap-1">
                            @foreach ($properties as $property)
                                <li>
                                    <a href="#" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm hover:bg-mist">
                                        <span class="font-semibold text-forest">{{ $property['name'] }}</span>
                                        <span class="text-muted">{{ $property['area'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
                @foreach (['Enterprise', 'Insights', 'About'] as $item)
                    <li><a href="#" class="rounded-lg px-3 py-2 text-[0.9375rem] font-medium text-forest hover:bg-mist">{{ $item }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="hidden items-center gap-2 lg:flex">
            <x-ui.button variant="ghost" href="#">Enquire</x-ui.button>
            <x-ui.button href="#" icon="calendar-days">Book a tour</x-ui.button>
        </div>

        <button
            type="button"
            popovertarget="mobile-menu"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-forest ring-1 ring-control ring-inset lg:hidden"
        >
            <x-heroicon-m-bars-3 class="size-5" aria-hidden="true" />
            Menu
        </button>
        @else
            <x-site.navigation.bar :items="$navigation['main']" :actions="$navigation['actions']" />
        @endif
    </div>

    @if ($navigation === null)
    <div
        id="mobile-menu"
        popover
        class="inset-0 m-0 h-dvh max-h-none w-full max-w-none overflow-y-auto border-0 bg-surface p-0 backdrop:bg-forest/40 lg:hidden"
    >
        <div class="container-page flex h-18 items-center justify-between border-b border-line">
            <x-site.brand-logo />
            <button
                type="button"
                popovertarget="mobile-menu"
                popovertargetaction="hide"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-forest ring-1 ring-control ring-inset"
            >
                <x-heroicon-m-x-mark class="size-5" aria-hidden="true" />
                Close
            </button>
        </div>
        <nav aria-label="Main" class="container-page flex flex-col gap-1 py-4">
            <details class="group rounded-lg">
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-2 py-3 font-semibold text-forest [&::-webkit-details-marker]:hidden">
                    Workspaces
                    <x-heroicon-m-chevron-down class="size-5 transition-transform group-open:rotate-180" aria-hidden="true" />
                </summary>
                <ul class="flex flex-col pb-2 pl-2">
                    @foreach ($categories as $category)
                        <li><a href="#" class="block rounded-lg px-2 py-2.5 text-ink hover:bg-mist">{{ $category['name'] }}</a></li>
                    @endforeach
                </ul>
            </details>
            <details class="group rounded-lg">
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-2 py-3 font-semibold text-forest [&::-webkit-details-marker]:hidden">
                    Locations
                    <x-heroicon-m-chevron-down class="size-5 transition-transform group-open:rotate-180" aria-hidden="true" />
                </summary>
                <ul class="flex flex-col pb-2 pl-2">
                    @foreach ($properties as $property)
                        <li><a href="#" class="block rounded-lg px-2 py-2.5 text-ink hover:bg-mist">{{ $property['name'] }}, {{ $property['area'] }}</a></li>
                    @endforeach
                </ul>
            </details>
            @foreach (['Enterprise', 'Insights', 'About'] as $item)
                <a href="#" class="rounded-lg px-2 py-3 font-semibold text-forest hover:bg-mist">{{ $item }}</a>
            @endforeach
            <div class="mt-4 grid gap-3 border-t border-line pt-6">
                <x-ui.button href="#" icon="calendar-days" size="lg">Book a tour</x-ui.button>
                <x-ui.button variant="secondary" href="#" size="lg">Enquire</x-ui.button>
            </div>
        </nav>
    </div>
    @else
        <x-site.navigation.sheet :items="$navigation['main']" :actions="$navigation['actions']" />
    @endif
</header>
