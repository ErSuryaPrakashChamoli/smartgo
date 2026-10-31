{{-- A published page, or a staff preview of one of its versions (BR-CMS-4): the same view renders both. --}}
<x-layouts::site
    :title="$title"
    :meta-title="$metaTitle"
    :description="$metaDescription"
    :noindex="$preview !== null"
    :robots="$robots"
    :canonical="$canonical"
    :navigation="$navigation"
    :share-image="$shareImage ?? null"
>
    @if ($preview !== null || ($template ?? 'standard') === 'sample')
        <x-slot:banner>
            @if ($preview !== null)
                <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
            @endif
            @if (($template ?? 'standard') === 'sample')
                <x-site.sample-content-notice />
            @endif
        </x-slot:banner>
    @endif

    @unless ($startsWithIntroduction)
        <div class="container-page flex flex-col gap-3 pt-12 pb-2 sm:pt-16">
            <h1 class="max-w-4xl font-display text-display-xl font-semibold text-balance text-forest">{{ $title }}</h1>
            @if (($template ?? 'standard') === 'legal')
                <p class="text-sm text-muted">{{ isset($lastUpdated) && $lastUpdated ? 'Last updated '.$lastUpdated->copy()->timezone(config('backoffice.timezone'))->format('j F Y') : 'Not published yet' }}</p>
            @endif
        </div>
    @endunless

    @foreach ($blocks as $block)
        <x-dynamic-component :component="$block['component']" :data="$block['data']" :preview="$preview !== null" />
    @endforeach
</x-layouts::site>
