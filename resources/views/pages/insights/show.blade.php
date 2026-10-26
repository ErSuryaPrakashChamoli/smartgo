{{-- An insights article, or a staff preview of one of its versions. --}}
<x-layouts::site :title="$title" :description="$lead" :noindex="$preview !== null" :canonical="$canonical ?? null" :navigation="$navigation">
    @if ($preview !== null)
        <x-slot:banner>
            <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
        </x-slot:banner>
    @endif

    <article>
        <header class="container-page pt-12 sm:pt-16">
            <div class="flex max-w-4xl flex-col gap-4">
                <p class="text-sm text-muted">{{ $date ? $date->copy()->timezone(config('backoffice.timezone'))->format('j F Y') : 'Not published yet' }}</p>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">{{ $title }}</h1>
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
