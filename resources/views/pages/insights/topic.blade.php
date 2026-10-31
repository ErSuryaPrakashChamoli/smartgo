{{-- One insight topic's published articles, newest first (IA /insights/topic/{slug}). --}}
<x-layouts::site :title="$topic->name.' — Insights'" :description="'Articles about '.$topic->name.' from SmartGro.'" :robots="$topic->isSample() ? \App\Models\SeoMetadata::NOINDEX : null" :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <div class="flex flex-col gap-3">
            <x-site.breadcrumb :items="[['label' => 'Home', 'href' => '/'], ['label' => 'Insights', 'href' => '/insights'], ['label' => $topic->name]]" />
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">{{ $topic->name }}</h1>
        </div>
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
    </div>
</x-layouts::site>
