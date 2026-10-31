{{-- Insights articles, newest first (FR-CMS-7). --}}
<x-layouts::site title="Insights" description="Articles and guides from SmartGro." :noindex="$posts->total() === 0 && ! $isFiltered" :robots="$isFiltered ? \App\Models\SeoMetadata::NOINDEX : null" :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Insights</h1>
            <form method="get" action="/insights" role="search" aria-label="Insights" class="flex w-full gap-2 lg:max-w-md">
                <label for="insights-q" class="sr-only">Search articles</label>
                <div class="relative flex-1">
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                    <input id="insights-q" type="search" name="q" value="{{ $query }}" maxlength="100" placeholder="Search articles" class="h-12 w-full rounded-full bg-surface pr-4 pl-10 text-ink ring-1 ring-control placeholder:text-muted focus:ring-2 focus:ring-leaf-700 focus:outline-none">
                </div>
                <x-ui.button type="submit" pill>Search</x-ui.button>
            </form>
        </div>
        @if ($isFiltered)
            <p class="-mt-4 text-muted" role="status">
                {{ $posts->total() }} {{ str('article')->plural($posts->total()) }} for “{{ $query }}”.
                <a href="/insights" class="font-semibold text-leaf-700 hover:underline">Show all articles</a>
            </p>
        @endif
        @if ($topics->isNotEmpty())
            <nav aria-label="Topics">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($topics as $topic)
                        <li><a href="{{ $topic->path() }}" class="inline-flex min-h-11 items-center rounded-full bg-mist px-4 text-sm font-semibold text-forest ring-1 ring-line hover:text-leaf-700">{{ $topic->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($posts->isEmpty())
            <p class="text-muted">{{ $isFiltered ? 'No article matches these words. Try other words, or browse the topics.' : 'No articles have been published yet.' }}</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($posts as $post)
                    <li class="flex flex-col gap-3 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                        <p class="text-sm text-muted">{{ $post->published_at?->copy()->timezone(config('backoffice.timezone'))->format('j F Y') }}</p>
                        <h2 class="font-display text-subheading font-semibold text-forest"><a href="{{ $post->path() }}" class="hover:text-leaf-700">{{ $post->title }}</a></h2>
                        <p class="text-muted">{{ $post->excerpt }}</p>
                        @if ($post->isSample())
                            <x-ui.badge tone="warning" icon="pencil-square" class="mt-auto self-start">Sample content</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ul>
            {{ $posts->links() }}
        @endif
    </div>
</x-layouts::site>
