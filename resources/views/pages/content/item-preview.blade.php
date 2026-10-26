{{-- A staff preview of an FAQ or testimonial version, shown as it will appear; never indexed. --}}
<x-layouts::site :title="'Preview: '.$heading" :noindex="true" :navigation="$navigation">
    <x-slot:banner>
        <x-site.revision-preview-banner :number="$preview['number']" :state="$preview['state']" :expires-at="$preview['expires_at']" />
    </x-slot:banner>

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
</x-layouts::site>
