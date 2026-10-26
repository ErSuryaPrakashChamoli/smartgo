@props(['data'])

<section class="container-page py-10 sm:py-14">
    <div class="flex max-w-3xl flex-col gap-6">
        <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
        <div>
            @foreach ($data['items'] as $item)
                <x-ui.faq-item :question="$item['question']">{!! nl2br(e($item['answer'])) !!}</x-ui.faq-item>
            @endforeach
        </div>
    </div>
</section>
