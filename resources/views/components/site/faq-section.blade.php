@props(['faqs', 'title', 'id' => 'faqs'])

{{-- Published questions about a location or a workspace type, in their set order (FR-CMS-7). --}}
<section id="{{ $id }}" aria-labelledby="{{ $id }}-heading" class="flex scroll-mt-36 flex-col gap-6">
    <x-ui.section-heading eyebrow="Questions" :title="$title" :id="$id.'-heading'" />
    <div class="max-w-3xl">
        @foreach ($faqs as $faq)
            <x-ui.faq-item :question="$faq->question">{!! nl2br(e($faq->answer)) !!}</x-ui.faq-item>
        @endforeach
    </div>
</section>
