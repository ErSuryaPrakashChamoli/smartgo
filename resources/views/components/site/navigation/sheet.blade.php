@props(['items', 'actions'])

{{--
    The full-screen menu below `lg`: groups and their categories expand in place, links show their descriptions
    (OD-149) in full, and the main header button comes first.
--}}
@php
    $isCurrent = fn (?string $href): bool => $href !== null && str_starts_with($href, '/') && trim($href, '/') === trim(request()->path(), '/');
@endphp

@if ($items !== [] || $actions !== [])
    <div
        id="mobile-menu"
        popover
        class="inset-0 m-0 h-dvh max-h-none w-full max-w-none overflow-y-auto border-0 bg-paper p-0 backdrop:bg-forest/40 lg:hidden"
    >
        <div class="container-page flex h-18 items-center justify-between border-b border-line">
            <x-site.brand-logo />
            <button
                type="button"
                popovertarget="mobile-menu"
                popovertargetaction="hide"
                aria-controls="mobile-menu"
                class="inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold text-forest ring-1 ring-control ring-inset hover:bg-mist"
            >
                <x-heroicon-m-x-mark class="size-5" aria-hidden="true" />
                Close
            </button>
        </div>
        <nav aria-label="Main" class="container-page flex flex-col gap-1 py-4">
            @foreach ($items as $item)
                @if ($item['children'] !== [])
                    <details class="group rounded-lg">
                        <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-2 py-3 font-semibold text-forest [&::-webkit-details-marker]:hidden">
                            {{ $item['label'] }}
                            <x-heroicon-m-chevron-down class="size-5 transition-transform group-open:rotate-180" aria-hidden="true" />
                        </summary>
                        <ul class="flex flex-col pb-2 pl-2">
                            @foreach ($item['children'] as $child)
                                <li>
                                    @if ($child['children'] !== [])
                                        <details class="group/sub">
                                            <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-lg px-2 py-3 font-semibold text-forest hover:bg-mist [&::-webkit-details-marker]:hidden">
                                                @if ($child['icon'])
                                                    <x-dynamic-component :component="'heroicon-o-'.$child['icon']" @class(['size-5 shrink-0 text-leaf-700', 'self-start mt-0.5' => $child['description']]) aria-hidden="true" />
                                                @endif
                                                <span class="flex flex-1 flex-col gap-0.5">
                                                    {{ $child['label'] }}
                                                    @if ($child['description'])
                                                        <span class="text-sm/snug font-normal text-muted">{{ $child['description'] }}</span>
                                                    @endif
                                                </span>
                                                <x-heroicon-m-chevron-down class="size-4 shrink-0 text-muted transition-transform group-open/sub:rotate-180" aria-hidden="true" />
                                            </summary>
                                            <ul aria-label="{{ $child['label'] }}" class="ml-4 flex flex-col divide-y divide-line border-l border-line pl-2">
                                                @foreach ($child['children'] as $link)
                                                    <li>
                                                        <a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif @if ($isCurrent($link['href'])) aria-current="page" @endif class="flex min-h-12 items-center gap-3 rounded-lg px-2 py-3 text-ink hover:bg-mist aria-[current=page]:font-semibold">
                                                            <x-dynamic-component :component="'heroicon-o-'.($link['icon'] ?? 'arrow-right')" @class(['size-5 shrink-0 text-forest', 'self-start mt-0.5' => $link['description']]) aria-hidden="true" />
                                                            <span class="flex flex-1 flex-col gap-0.5">
                                                                <span class="flex items-center gap-2">
                                                                    {{ $link['label'] }}
                                                                    @if (str_starts_with($link['href'], '/enquire'))
                                                                        <span class="rounded-full bg-mist px-2 py-0.5 text-xs font-semibold text-leaf-700">Ask us</span>
                                                                    @endif
                                                                </span>
                                                                @if ($link['description'])
                                                                    <span class="text-sm/snug text-muted">{{ $link['description'] }}</span>
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            @if ($child['sample'])
                                                <p class="ml-4 flex items-start gap-2 border-l border-line px-4 py-3 text-sm text-muted">
                                                    <x-heroicon-m-information-circle class="mt-0.5 size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                                                    Sample pages: example text, not yet confirmed by SmartGro.
                                                </p>
                                            @endif
                                        </details>
                                    @else
                                        <a href="{{ $child['href'] }}" @if ($child['external']) rel="noopener" @endif @if ($isCurrent($child['href'])) aria-current="page" @endif class="block rounded-lg px-2 py-3 text-ink hover:bg-mist aria-[current=page]:font-semibold">{{ $child['label'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <a href="{{ $item['href'] }}" @if ($item['external']) rel="noopener" @endif @if ($isCurrent($item['href'])) aria-current="page" @endif class="rounded-lg px-2 py-3 font-semibold text-forest hover:bg-mist aria-[current=page]:bg-mist">{{ $item['label'] }}</a>
                @endif
            @endforeach
            <a href="{{ route('search', [], false) }}" @if (request()->routeIs('search')) aria-current="page" @endif class="inline-flex items-center gap-2 rounded-lg px-2 py-3 font-semibold text-forest hover:bg-mist aria-[current=page]:bg-mist">
                <x-heroicon-m-magnifying-glass class="size-5 text-leaf-700" aria-hidden="true" />
                Search
            </a>
            @if ($actions !== [])
                <div class="mt-4 grid gap-3 border-t border-line pt-6">
                    @foreach (array_reverse($actions) as $action)
                        <x-ui.button size="lg" pill :variant="$loop->first ? 'primary' : 'secondary'" :href="$action['href']" :rel="$action['external'] ? 'noopener' : null">{{ $action['label'] }}</x-ui.button>
                    @endforeach
                </div>
            @endif
        </nav>
    </div>
@endif
