{{-- Insights articles, newest first (FR-CMS-7). --}}
<x-layouts::site title="Insights" description="Articles and guides from SmartGro." :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Insights</h1>
        @if ($posts->isEmpty())
            <p class="text-muted">No articles have been published yet.</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <li class="flex flex-col gap-3 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                        <p class="text-sm text-muted">{{ $post->published_at?->copy()->timezone(config('backoffice.timezone'))->format('j F Y') }}</p>
                        <h2 class="font-display text-subheading font-semibold text-forest"><a href="{{ $post->path() }}" class="hover:text-leaf-700">{{ $post->title }}</a></h2>
                        <p class="text-muted">{{ $post->excerpt }}</p>
                    </li>
                @endforeach
            </ul>
            {{ $posts->links() }}
        @endif
    </div>
</x-layouts::site>
