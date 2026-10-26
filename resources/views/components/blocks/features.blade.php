@props(['data'])

@php
    $hasHeading = filled($data['heading']);
@endphp

<section class="container-page py-10 sm:py-14">
    <div class="flex flex-col gap-8">
        @if ($hasHeading || filled($data['intro']))
            <div class="flex max-w-3xl flex-col gap-3">
                @if ($hasHeading)
                    <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
                @endif
                @if (filled($data['intro']))
                    <p class="text-lead text-pretty text-muted">{{ $data['intro'] }}</p>
                @endif
            </div>
        @endif
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($data['items'] as $item)
                <li class="flex flex-col gap-2 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                    <{{ $hasHeading ? 'h3' : 'h2' }} class="font-display text-subheading font-semibold text-forest">{{ $item['title'] }}</{{ $hasHeading ? 'h3' : 'h2' }}>
                    <p class="text-muted">{{ $item['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
