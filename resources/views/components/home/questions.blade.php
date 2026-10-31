@props([
    'faqs',
])

{{-- The first published general questions, in their set order, with the full list one link away. --}}
<section class="py-14 sm:py-20" aria-labelledby="questions-heading">
    <div class="container-page grid gap-8 lg:grid-cols-12">
        <div class="flex flex-col gap-4 lg:col-span-4" data-reveal>
            <h2 id="questions-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Questions</h2>
            <p class="text-lg text-pretty text-muted">What people often ask SmartGro.</p>
            <a href="/faqs" class="group inline-flex items-center gap-1.5 self-start rounded-full px-1 text-[0.9375rem] font-semibold text-leaf-700 hover:text-leaf-800">
                All questions
                <x-heroicon-m-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </a>
        </div>
        <div class="lg:col-span-8" data-reveal>
            @foreach ($faqs as $faq)
                <x-ui.faq-item :question="$faq->question">{!! nl2br(e($faq->answer)) !!}</x-ui.faq-item>
            @endforeach
        </div>
    </div>
</section>
