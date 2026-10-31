@props([
    'posts',
])

{{-- The newest published articles; sample articles are labelled as such (OD-143). --}}
<section class="py-14 sm:py-20" aria-labelledby="insights-heading">
    <div class="container-page flex flex-col gap-6 sm:gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" data-reveal>
            <div class="flex max-w-2xl flex-col gap-2">
                <h2 id="insights-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Latest insights</h2>
                <p class="text-lg text-pretty text-muted">Guides and articles about flexible work and choosing a workspace.</p>
            </div>
            <a href="/insights" class="group inline-flex shrink-0 items-center gap-1.5 self-start rounded-full px-1 text-[0.9375rem] font-semibold text-leaf-700 hover:text-leaf-800 sm:self-auto">
                All insights
                <x-heroicon-m-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </a>
        </div>

        <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
            @foreach ($posts as $post)
                <li class="relative flex flex-col gap-3 rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line transition duration-300 has-[a:hover]:-translate-y-1 has-[a:hover]:shadow-raised">
                    <p class="text-sm text-muted">{{ $post->published_at?->copy()->timezone(config('backoffice.timezone'))->format('j F Y') }}</p>
                    <h3 class="font-display text-subheading font-semibold text-forest">
                        <a href="{{ $post->path() }}" class="rounded-sm after:absolute after:inset-0 hover:text-leaf-700">{{ $post->title }}</a>
                    </h3>
                    @if (filled($post->excerpt))
                        <p class="text-muted">{{ $post->excerpt }}</p>
                    @endif
                    @if ($post->isSample())
                        <x-ui.badge tone="warning" icon="pencil-square" class="mt-auto self-start">Sample content</x-ui.badge>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
