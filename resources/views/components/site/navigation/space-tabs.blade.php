@props(['item', 'index'])

{{--
    A drop-down of space types (OD-177), unlike Solutions' list of categories: the space types as a row of tabs, and
    under the chosen one its spaces in a column per location. The script in resources/js/app.js shows one type at a
    time (the first by default; the arrow keys move between tabs); without it every type's spaces show, one under the
    other. Plain links in the group, such as "All workspace types", sit at the foot, with a note when any space shown
    is an example.
--}}
@php
    $types = array_values(array_filter($item['children'], fn (array $child): bool => $child['children'] !== []));
    $links = array_values(array_filter($item['children'], fn (array $child): bool => $child['children'] === []));
@endphp

<div class="flex flex-col gap-4" data-flyout>
    {{-- More than four tabs sit in an even grid of three a row instead of wrapping unevenly. --}}
    <ul @class(['border-b border-line pb-3', 'grid grid-cols-3 gap-1.5' => count($types) > 4, 'flex flex-wrap gap-1.5' => count($types) <= 4]) aria-label="{{ $item['label'] }}: space types">
        @foreach ($types as $type)
            <li>
                <button type="button" data-flyout-tab aria-controls="nav-group-{{ $index }}-{{ $loop->index }}"
                    class="inline-flex min-h-11 w-full items-center gap-2 rounded-full px-3.5 py-2 text-sm font-semibold text-ink ring-1 ring-line ring-inset transition-colors hover:bg-mist hover:text-forest aria-expanded:bg-forest aria-expanded:text-white aria-expanded:ring-forest">
                    @if ($type['icon'])
                        <x-dynamic-component :component="'heroicon-o-'.$type['icon']" class="size-4 shrink-0" aria-hidden="true" />
                    @endif
                    <span class="flex-1 text-left">{{ $type['label'] }}</span>
                    <span class="rounded-full bg-black/5 px-1.5 text-xs font-medium tabular-nums" aria-label="{{ count($type['children']) }} {{ count($type['children']) === 1 ? 'space' : 'spaces' }}">{{ count($type['children']) }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    @foreach ($types as $type)
        @php
            $byLocation = collect($type['children'])->groupBy(fn (array $link): string => $link['location'] ?? '');
        @endphp
        <div id="nav-group-{{ $index }}-{{ $loop->index }}" class="flex flex-col gap-3" data-flyout-pane>
            <p class="px-2 text-sm text-muted">
                {{ $type['description'] ?? count($type['children']).' '.(count($type['children']) === 1 ? 'space' : 'spaces').' at '.$byLocation->count().' '.($byLocation->count() === 1 ? 'location' : 'locations') }}
            </p>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(12.5rem,1fr))] gap-x-3 gap-y-4">
                @foreach ($byLocation as $location => $spaces)
                    <section @if ($location !== '') aria-labelledby="nav-group-{{ $index }}-{{ $loop->parent->index }}-{{ $loop->index }}-at" @endif class="flex flex-col gap-1 rounded-2xl bg-paper p-2 ring-1 ring-line">
                        @if ($location !== '')
                            <h3 id="nav-group-{{ $index }}-{{ $loop->parent->index }}-{{ $loop->index }}-at" class="flex flex-col px-2 pt-1">
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-forest">
                                    <x-heroicon-m-map-pin class="size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                                    {{ $location }}
                                </span>
                                @if (filled($spaces->first()['area'] ?? null))
                                    <span class="pl-5.5 text-xs text-muted">{{ $spaces->first()['area'] }}</span>
                                @endif
                            </h3>
                        @endif
                        <ul class="flex flex-col">
                            @foreach ($spaces as $space)
                                <li>
                                    <a href="{{ $space['href'] }}" @if (trim($space['href'], '/') === trim(request()->path(), '/')) aria-current="page" @endif
                                        class="group/space flex min-h-11 items-center justify-between gap-2 rounded-xl px-2 py-2 text-[0.9375rem] font-medium text-ink hover:bg-white hover:text-forest aria-[current=page]:bg-white aria-[current=page]:text-forest">
                                        {{ $space['label'] }}
                                        <x-heroicon-m-arrow-right class="size-4 shrink-0 text-leaf-700 opacity-0 transition-opacity group-hover/space:opacity-100" aria-hidden="true" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </div>
    @endforeach

    @if ($links !== [] || $item['sample'])
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-2 pt-3">
            @if ($item['sample'])
                <p class="flex items-center gap-2 text-xs text-muted">
                    <x-heroicon-m-information-circle class="size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                    Example spaces: not yet confirmed by SmartGro.
                </p>
            @endif
            @if ($links !== [])
                <ul class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($links as $link)
                        <li>
                            <a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-leaf-700 hover:text-leaf-800">
                                {{ $link['label'] }}
                                <x-heroicon-m-arrow-right class="size-4" aria-hidden="true" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
