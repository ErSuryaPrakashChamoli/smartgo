@props(['items', 'actions'])

{{-- The full-screen menu below `lg`: groups expand in place, and the main header button comes first. --}}
@php
    $isCurrent = fn (?string $href): bool => $href !== null && str_starts_with($href, '/') && trim($href, '/') === trim(request()->path(), '/');
@endphp

@if ($items !== [] || $actions !== [])
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
                                    <a href="{{ $child['href'] }}" @if ($child['external']) rel="noopener" @endif @if ($isCurrent($child['href'])) aria-current="page" @endif class="block rounded-lg px-2 py-2.5 text-ink hover:bg-mist aria-[current=page]:font-semibold">{{ $child['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <a href="{{ $item['href'] }}" @if ($item['external']) rel="noopener" @endif @if ($isCurrent($item['href'])) aria-current="page" @endif class="rounded-lg px-2 py-3 font-semibold text-forest hover:bg-mist aria-[current=page]:bg-mist">{{ $item['label'] }}</a>
                @endif
            @endforeach
            @if ($actions !== [])
                <div class="mt-4 grid gap-3 border-t border-line pt-6">
                    @foreach (array_reverse($actions) as $action)
                        <x-ui.button size="lg" :variant="$loop->first ? 'primary' : 'secondary'" :href="$action['href']" :rel="$action['external'] ? 'noopener' : null">{{ $action['label'] }}</x-ui.button>
                    @endforeach
                </div>
            @endif
        </nav>
    </div>
@endif
