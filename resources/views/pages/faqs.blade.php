{{-- Published FAQs in their set order (FR-CMS-7). --}}
<x-layouts::site title="Frequently asked questions" description="Answers to common questions about SmartGro workspaces." :canonical="$canonical" :navigation="$navigation">
    <div class="container-page py-12 sm:py-16">
        <div class="flex max-w-3xl flex-col gap-8">
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Frequently asked questions</h1>
            @if ($faqs->isEmpty())
                <p class="text-muted">No questions have been published yet.</p>
            @else
                <div>
                    @foreach ($faqs as $faq)
                        <x-ui.faq-item :question="$faq->question">{!! nl2br(e($faq->answer)) !!}</x-ui.faq-item>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts::site>
