@props(['items', 'actions'])

{{--
    The published main menu and header buttons from `lg` up; below `lg` the Menu button opens the sheet.
    Labels are compact until `xl`, and there only the main button (the last) shows, so up to seven menu
    items, search and the button fit at 1024 px; the other button is in the sheet and the phone action bar.
--}}
@php
    $isCurrent = fn (?string $href): bool => $href !== null && str_starts_with($href, '/') && trim($href, '/') === trim(request()->path(), '/');
@endphp

@if ($items !== [])
    <nav aria-label="Main" class="hidden lg:block">
        <ul class="flex items-center gap-1">
            @foreach ($items as $item)
                <li>
                    @if ($item['children'] !== [])
                        <button
                            type="button"
                            popovertarget="nav-group-{{ $loop->index }}"
                            class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-medium whitespace-nowrap text-ink hover:bg-mist hover:text-forest xl:px-4 xl:text-[0.9375rem] [&:has(+:popover-open)]:bg-mist [&:has(+:popover-open)_svg]:rotate-180"
                        >
                            {{ $item['label'] }}
                            <x-heroicon-m-chevron-down class="size-4 transition-transform" aria-hidden="true" />
                        </button>
                        @php
                            $subGroups = array_filter($item['children'], fn (array $child): bool => $child['children'] !== []);
                            $hasDescriptions = collect($subGroups)->flatMap(fn (array $child): array => [$child, ...$child['children']])->contains(fn (array $entry): bool => $entry['description'] !== null);
                        @endphp
                        <div
                            id="nav-group-{{ $loop->index }}"
                            popover
                            @class([
                                'nav-group-panel inset-auto top-22 left-1/2 m-0 max-h-[calc(100dvh-7rem)] -translate-x-1/2 overflow-y-auto overscroll-contain rounded-3xl border-0 bg-surface p-3 shadow-raised ring-1 ring-line xl:top-25',
                                'w-[min(46rem,calc(100vw-2.5rem))]' => $subGroups !== [] && $hasDescriptions,
                                'w-[min(38rem,calc(100vw-2.5rem))]' => $subGroups !== [] && ! $hasDescriptions,
                                'w-[min(22rem,calc(100vw-2.5rem))]' => $subGroups === [] && count($item['children']) <= 6,
                                'w-[min(36rem,calc(100vw-2.5rem))]' => $subGroups === [] && count($item['children']) > 6,
                            ])
                        >
                            @if ($subGroups !== [])
                                {{--
                                    Categories on the left, the active category's links on the right (OD-145), each with the
                                    description and icon chosen in the menu editor (OD-149). The script in resources/js/app.js
                                    makes each category a disclosure button (pointer, click or keyboard) and shows one list at a
                                    time; without it every list shows under the others. Descriptions are read as descriptions,
                                    not as part of the names, and keep to one line on short screens so a long list fits.
                                    A list leading to sample pages ends with a note saying so.
                                --}}
                                <div class="grid grid-cols-[minmax(12rem,15rem)_1fr]" data-flyout>
                                    <ul class="flex flex-col gap-1 border-r border-line py-1 pr-3">
                                        @foreach ($item['children'] as $child)
                                            <li @class(['border-t border-line pt-1' => ! $loop->first])>
                                                @if ($child['children'] !== [])
                                                    <button type="button" data-flyout-tab aria-controls="nav-group-{{ $loop->parent->index }}-{{ $loop->index }}" @if ($child['description']) aria-describedby="nav-group-{{ $loop->parent->index }}-{{ $loop->index }}-about" @endif
                                                        class="flex min-h-12 w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left text-[0.9375rem] font-semibold text-ink hover:bg-mist aria-expanded:bg-mist aria-expanded:text-forest">
                                                        @if ($child['icon'])
                                                            <x-dynamic-component :component="'heroicon-o-'.$child['icon']" @class(['size-5 shrink-0 text-leaf-700', 'self-start mt-0.5' => $child['description']]) aria-hidden="true" />
                                                        @endif
                                                        <span class="flex flex-1 flex-col gap-0.5">
                                                            {{ $child['label'] }}
                                                            @if ($child['description'])
                                                                <span id="nav-group-{{ $loop->parent->index }}-{{ $loop->index }}-about" aria-hidden="true" class="text-xs/snug font-normal text-muted">{{ $child['description'] }}</span>
                                                            @endif
                                                        </span>
                                                        <x-heroicon-m-chevron-right class="size-4 shrink-0 text-muted" aria-hidden="true" />
                                                    </button>
                                                @else
                                                    <a href="{{ $child['href'] }}" @if ($child['external']) rel="noopener" @endif @if ($isCurrent($child['href'])) aria-current="page" @endif class="flex min-h-12 items-center gap-3 rounded-2xl px-3 py-2.5 text-[0.9375rem] font-semibold text-ink hover:bg-mist aria-[current=page]:bg-mist">{{ $child['label'] }}</a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="flex flex-col gap-3 pl-3">
                                        @foreach ($item['children'] as $child)
                                            @if ($child['children'] !== [])
                                                <div id="nav-group-{{ $loop->parent->index }}-{{ $loop->index }}" class="flex flex-col gap-2" data-flyout-pane>
                                                    <ul aria-label="{{ $child['label'] }}" class="flex flex-col divide-y divide-line">
                                                        @foreach ($child['children'] as $link)
                                                            @php
                                                                $about = $link['description'] ? 'nav-link-'.$loop->parent->parent->index.'-'.$loop->parent->index.'-'.$loop->index : null;
                                                            @endphp
                                                            <li>
                                                                <a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif @if ($isCurrent($link['href'])) aria-current="page" @endif @if ($about) aria-describedby="{{ $about }}" @endif
                                                                    class="flex min-h-12 items-center gap-3 rounded-xl px-3 py-2 text-ink hover:bg-mist hover:text-forest aria-[current=page]:text-forest">
                                                                    <x-dynamic-component :component="'heroicon-o-'.($link['icon'] ?? 'arrow-right')" @class(['size-5 shrink-0 text-forest', 'self-start mt-0.5' => $about]) aria-hidden="true" />
                                                                    <span class="flex flex-1 flex-col gap-0.5">
                                                                        <span class="flex items-center gap-2 text-[0.9375rem] font-medium">
                                                                            {{ $link['label'] }}
                                                                            @if (str_starts_with($link['href'], '/enquire'))
                                                                                <span class="rounded-full bg-mist px-2 py-0.5 text-xs font-semibold text-leaf-700">Ask us</span>
                                                                            @endif
                                                                        </span>
                                                                        @if ($about)
                                                                            <span id="{{ $about }}" aria-hidden="true" class="line-clamp-2 text-[0.8125rem]/snug text-muted [@media(max-height:50rem)]:line-clamp-1">{{ $link['description'] }}</span>
                                                                        @endif
                                                                    </span>
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    @if ($child['sample'])
                                                        <p class="flex items-center gap-2 border-t border-line px-3 pt-2.5 text-xs text-muted">
                                                            <x-heroicon-m-information-circle class="size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                                                            Sample pages: example text, not yet confirmed by SmartGro.
                                                        </p>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <ul @class(['grid gap-1', 'grid-cols-2' => count($item['children']) > 6])>
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['href'] }}" @if ($child['external']) rel="noopener" @endif @if ($isCurrent($child['href'])) aria-current="page" @endif class="block rounded-2xl px-3 py-2.5 text-sm font-semibold text-forest hover:bg-mist aria-[current=page]:bg-mist">{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @else
                        <a href="{{ $item['href'] }}" @if ($item['external']) rel="noopener" @endif @if ($isCurrent($item['href'])) aria-current="page" @endif class="rounded-full px-3 py-2 text-sm font-medium whitespace-nowrap text-ink hover:bg-mist hover:text-forest aria-[current=page]:bg-mist aria-[current=page]:text-forest xl:px-4 xl:text-[0.9375rem]">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif

{{-- The right-hand group: search (the website's own search, OD-143), the header buttons from `lg`, the Menu button below it. --}}
<div class="flex items-center gap-2">
    <a
        href="{{ route('search', [], false) }}"
        @if (request()->routeIs('search')) aria-current="page" @endif
        class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-forest hover:bg-mist aria-[current=page]:bg-mist"
    >
        <x-heroicon-o-magnifying-glass class="size-5" aria-hidden="true" />
        <span class="sr-only">Search</span>
    </a>

    @if ($actions !== [])
        <div class="hidden items-center gap-2 lg:flex">
            @foreach ($actions as $action)
                <div @class(['hidden xl:block' => ! $loop->last])>
                    <x-ui.button pill :variant="$loop->last ? 'primary' : 'ghost'" :href="$action['href']" :rel="$action['external'] ? 'noopener' : null">{{ $action['label'] }}</x-ui.button>
                </div>
            @endforeach
        </div>
    @endif

    @if ($items !== [] || $actions !== [])
        <button
            type="button"
            popovertarget="mobile-menu"
            aria-controls="mobile-menu"
            class="inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold text-forest ring-1 ring-control ring-inset hover:bg-mist lg:hidden"
        >
            <x-heroicon-m-bars-3 class="size-5" aria-hidden="true" />
            Menu
        </button>
    @endif
</div>
