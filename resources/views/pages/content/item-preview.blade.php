{{-- A staff preview of an FAQ, testimonial, home banner or highlight version, shown as it will appear; never indexed. --}}
<x-layouts::site :title="'Preview: '.$heading" :noindex="true" :navigation="$navigation">
    <x-slot:banner>
        <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
    </x-slot:banner>

    @isset($banner)
        <div class="container-page pt-12 sm:pt-16">
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Preview: {{ $heading }}</h1>
        </div>
        <div class="py-8">
            @if ($banner['slide'])
                <x-home.carousel :slides="[$banner['slide']]" is-preview />
            @else
                <p class="container-page text-lg text-muted">Choose an image from the media library to see the banner. An image whose licence has expired is not shown.</p>
            @endif
        </div>
    @endisset

    @isset($highlight)
        <div class="container-page pt-12 pb-8 sm:pt-16">
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Preview: {{ $heading }}</h1>
        </div>
        <div class="pb-16">
            <x-home.highlights :items="[$highlight]" :sample="$highlight['sample']" />
        </div>
    @endisset

    @if (! isset($banner) && ! isset($highlight))
        <div class="container-page py-12 sm:py-16">
            <div class="flex max-w-3xl flex-col gap-6">
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Preview: {{ $heading }}</h1>
                @if ($faq)
                    <x-ui.faq-item :question="(string) $faq['question']" open>{!! nl2br(e((string) $faq['answer'])) !!}</x-ui.faq-item>
                @endif
                @if ($testimonial)
                    <x-blocks.testimonial :data="['testimonial' => $testimonial]" />
                @endif
            </div>
        </div>
    @endif
</x-layouts::site>
