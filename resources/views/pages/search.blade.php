{{-- The website's search (OD-143): results from published content only, grouped by kind; never indexed. --}}
<x-layouts::site title="Search" :noindex="true" :navigation="$navigation">
    <section class="container-page flex flex-col gap-8 py-10 sm:py-14" aria-labelledby="search-heading">
        <div class="flex max-w-3xl flex-col gap-3">
            <h1 id="search-heading" class="font-display text-display-lg font-semibold text-forest">Search SmartGro</h1>
            <p class="text-lg text-muted">Find locations, workspace types, pages, articles and answers.</p>
        </div>

        <form method="get" action="{{ route('search', [], false) }}" role="search" class="flex max-w-3xl flex-col gap-3 sm:flex-row">
            <label for="search-query" class="sr-only">Search</label>
            <div class="relative flex-1">
                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted" aria-hidden="true" />
                <input
                    id="search-query"
                    type="search"
                    name="q"
                    value="{{ $query }}"
                    maxlength="{{ \App\Services\Website\SiteSearch::MAX_LENGTH }}"
                    placeholder="For example: meeting rooms, Sector 62, virtual office"
                    class="h-13 w-full rounded-full border-0 bg-surface pr-5 pl-12 text-base text-ink ring-1 ring-control ring-inset placeholder:text-muted"
                    @if ($query === '') autofocus @endif
                >
            </div>
            <x-ui.button type="submit" size="lg" pill icon="arrow-right">Search</x-ui.button>
        </form>

        <div aria-live="polite" class="flex flex-col gap-8">
            @if ($query === '')
                <p class="text-muted">Type what you are looking for, such as a workspace type or an area of the city.</p>
            @elseif ($tooShort)
                <p class="text-muted">Type at least {{ \App\Services\Website\SiteSearch::MIN_LENGTH }} characters.</p>
            @elseif ($groups === [])
                <div class="flex flex-col gap-3 rounded-3xl bg-surface p-6 ring-1 ring-line">
                    <p class="font-semibold text-forest">Nothing found for “{{ $query }}”</p>
                    <p class="text-muted">Try another word, or browse <a href="/workspaces" class="font-semibold text-leaf-700 underline underline-offset-2">all workspace types</a>.</p>
                </div>
            @else
                <p class="text-sm text-muted">Results for “{{ $query }}”</p>
                @foreach ($groups as $group)
                    <section class="flex flex-col gap-3" aria-labelledby="search-group-{{ $loop->index }}">
                        <h2 id="search-group-{{ $loop->index }}" class="text-sm font-semibold tracking-wide text-muted uppercase">{{ $group['label'] }}</h2>
                        <ul class="flex flex-col divide-y divide-line overflow-hidden rounded-3xl bg-surface ring-1 ring-line">
                            @foreach ($group['results'] as $result)
                                <li>
                                    <a href="{{ $result['href'] }}" class="group flex items-start justify-between gap-4 px-5 py-4 hover:bg-mist">
                                        <span class="flex flex-col gap-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-forest group-hover:text-leaf-700">{{ $result['title'] }}</span>
                                                @if ($result['sample'])
                                                    <x-ui.badge tone="warning" icon="pencil-square">Sample content</x-ui.badge>
                                                @elseif ($result['badge'])
                                                    <x-ui.badge tone="info" icon="clock">{{ $result['badge'] }}</x-ui.badge>
                                                @endif
                                            </span>
                                            @if ($result['summary'])
                                                <span class="text-sm text-muted">{{ $result['summary'] }}</span>
                                            @endif
                                        </span>
                                        <x-heroicon-m-arrow-right class="mt-1 size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            @endif
        </div>
    </section>
</x-layouts::site>
