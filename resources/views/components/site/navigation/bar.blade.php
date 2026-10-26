@props(['items', 'actions'])

{{-- The published main menu and header buttons from `lg` up; below `lg` the Menu button opens the sheet. --}}
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
                            class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-[0.9375rem] font-medium text-forest hover:bg-mist [&:has(+:popover-open)]:bg-mist [&:has(+:popover-open)_svg]:rotate-180"
                        >
                            {{ $item['label'] }}
                            <x-heroicon-m-chevron-down class="size-4 transition-transform" aria-hidden="true" />
                        </button>
                        <div
                            id="nav-group-{{ $loop->index }}"
                            popover
                            class="inset-auto top-22 left-1/2 m-0 w-[min(22rem,calc(100vw-2.5rem))] -translate-x-1/2 rounded-2xl border-0 bg-surface p-3 shadow-raised ring-1 ring-line"
                        >
                            <ul class="flex flex-col gap-1">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['href'] }}" @if ($child['external']) rel="noopener" @endif @if ($isCurrent($child['href'])) aria-current="page" @endif class="block rounded-lg px-3 py-2 text-sm font-semibold text-forest hover:bg-mist aria-[current=page]:bg-mist">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <a href="{{ $item['href'] }}" @if ($item['external']) rel="noopener" @endif @if ($isCurrent($item['href'])) aria-current="page" @endif class="rounded-lg px-3 py-2 text-[0.9375rem] font-medium text-forest hover:bg-mist aria-[current=page]:bg-mist">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif

@if ($actions !== [])
    <div class="hidden items-center gap-2 lg:flex">
        @foreach ($actions as $action)
            <x-ui.button :variant="$loop->last ? 'primary' : 'ghost'" :href="$action['href']" :rel="$action['external'] ? 'noopener' : null">{{ $action['label'] }}</x-ui.button>
        @endforeach
    </div>
@endif

@if ($items !== [] || $actions !== [])
    <button
        type="button"
        popovertarget="mobile-menu"
        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-forest ring-1 ring-control ring-inset lg:hidden"
    >
        <x-heroicon-m-bars-3 class="size-5" aria-hidden="true" />
        Menu
    </button>
@endif
