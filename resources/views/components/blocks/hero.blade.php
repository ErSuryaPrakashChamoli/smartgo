@props(['data'])

<section class="relative isolate overflow-hidden">
    <div class="absolute inset-0 -z-10 bg-linear-to-b from-mist/70 to-paper" aria-hidden="true"></div>
    <div class="container-page flex flex-col gap-6 py-12 sm:py-16 lg:py-20">
        @if (filled($data['eyebrow']))
            <p class="text-eyebrow font-semibold text-leaf-700 uppercase">{{ $data['eyebrow'] }}</p>
        @endif
        <h1 class="max-w-4xl font-display text-display-2xl font-semibold text-balance text-forest">{{ $data['heading'] }}</h1>
        @if (filled($data['intro']))
            <p class="max-w-2xl text-lead text-pretty text-muted">{{ $data['intro'] }}</p>
        @endif
        @if ($data['link'])
            <div>
                <x-ui.button size="lg" :href="$data['link']['href']" :rel="$data['link']['external'] ? 'noopener' : null">{{ $data['link']['label'] }}</x-ui.button>
            </div>
        @endif
    </div>
</section>
