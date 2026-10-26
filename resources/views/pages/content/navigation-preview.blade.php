{{-- A staff preview of one menu version in the real header and footer (FR-CMS-4); never indexed. --}}
<x-layouts::site :title="'Preview: '.$menuName" :noindex="true" :navigation="$navigation">
    <x-slot:banner>
        <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
    </x-slot:banner>

    <div class="container-page flex flex-col gap-4 py-16 sm:py-20">
        <h1 class="max-w-3xl font-display text-display-xl font-semibold text-balance text-forest">Preview: {{ $menuName }}</h1>
        <p class="max-w-2xl text-lead text-pretty text-muted">This page shows version {{ $preview['number'] }} of the {{ $menuName }} in the header and footer, with the other menus as they are live. Links to pages that are not published are hidden, as they will be on the website.</p>
    </div>
</x-layouts::site>
