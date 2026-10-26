@props(['data'])

@php
    $hasHeading = filled($data['heading']);
@endphp

<section class="container-page py-10 sm:py-14">
    <div class="flex flex-col gap-8">
        @if ($hasHeading)
            <h2 class="max-w-3xl font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
        @endif
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($data['items'] as $item)
                <div class="flex flex-col-reverse gap-1 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                    <dt class="text-muted">{{ $item['label'] }}</dt>
                    <dd class="font-display text-display-lg font-semibold text-forest">{{ $item['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
