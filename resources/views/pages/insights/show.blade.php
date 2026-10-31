{{-- An insights article, or a staff preview of one of its versions. --}}
<x-layouts::site :title="$title" :description="$lead" :noindex="$preview !== null" :robots="($isSample ?? false) ? \App\Models\SeoMetadata::NOINDEX : null" :canonical="$canonical ?? null" :navigation="$navigation">
    @if ($preview !== null || ($isSample ?? false))
        <x-slot:banner>
            @if ($preview !== null)
                <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
            @endif
            @if ($isSample ?? false)
                <x-site.sample-content-notice />
            @endif
        </x-slot:banner>
    @endif

    <article>
        <header class="container-page pt-12 sm:pt-16">
            <div class="flex max-w-4xl flex-col gap-4">
                <p class="text-sm text-muted">{{ $date ? $date->copy()->timezone(config('backoffice.timezone'))->format('j F Y') : 'Not published yet' }}</p>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">{{ $title }}</h1>
                @if (! empty($topics ?? []))
                    <ul aria-label="Topics" class="flex flex-wrap gap-2">
                        @foreach ($topics as $topic)
                            <li>
                                @if ($topic['path'])
                                    <a href="{{ $topic['path'] }}" class="inline-flex min-h-11 items-center rounded-full bg-mist px-4 text-sm font-semibold text-forest ring-1 ring-line hover:text-leaf-700">{{ $topic['name'] }}</a>
                                @else
                                    <span class="inline-flex min-h-11 items-center rounded-full bg-mist px-4 text-sm font-semibold text-forest ring-1 ring-line">{{ $topic['name'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (filled($lead))
                    <p class="text-lead text-pretty text-muted">{{ $lead }}</p>
                @endif
            </div>
        </header>
        @foreach ($blocks as $block)
            <x-dynamic-component :component="$block['component']" :data="$block['data']" />
        @endforeach
    </article>
</x-layouts::site>
