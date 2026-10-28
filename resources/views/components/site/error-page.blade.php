@props([
    'status',
    'title',
    'navigation',
    'links' => true,
])

{{-- An error page in the website's shell (Phase 6): never indexed, nothing about the request or the error is shown. --}}
<x-layouts::site :title="$title" :noindex="true" :navigation="$navigation">
    <div class="container-page flex flex-col items-start gap-6 py-16 sm:py-24">
        <p class="text-eyebrow font-semibold tracking-wide text-leaf-700 uppercase">Error {{ $status }}</p>
        <h1 class="max-w-3xl font-display text-display-xl font-semibold text-balance text-forest">{{ $title }}</h1>
        <div class="max-w-2xl text-lead text-pretty text-muted">{{ $slot }}</div>
        <div class="flex flex-col gap-3 sm:flex-row">
            <x-ui.button href="/" icon="home">Go to the home page</x-ui.button>
            @if ($links)
                <x-ui.button variant="secondary" href="/locations" icon="map-pin">See the locations</x-ui.button>
                <x-ui.button variant="secondary" href="/workspaces" icon="building-office">See the workspaces</x-ui.button>
            @endif
        </div>
    </div>
</x-layouts::site>
