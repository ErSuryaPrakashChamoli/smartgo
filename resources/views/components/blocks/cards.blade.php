@props(['data'])

@php
    $hasHeading = filled($data['heading']);
    $count = count($data['items']);
@endphp

{{-- Cards with links (LinkCardsBlock): a card whose link is live is one large target, its link label shown at the bottom. --}}
<section class="container-page py-10 sm:py-14">
    <div class="flex flex-col gap-8">
        @if ($hasHeading || filled($data['intro']))
            <div class="flex max-w-3xl flex-col gap-3" data-reveal>
                @if ($hasHeading)
                    <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
                @endif
                @if (filled($data['intro']))
                    <p class="text-lg text-pretty text-muted">{{ $data['intro'] }}</p>
                @endif
            </div>
        @endif
        <ul data-reveal-group @class(['grid gap-5 sm:grid-cols-2', 'lg:grid-cols-3' => $count !== 4, 'lg:grid-cols-4' => $count === 4])>
            @foreach ($data['items'] as $item)
                <li class="group relative flex flex-col gap-3 rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line transition duration-300 has-[a:hover]:-translate-y-1 has-[a:hover]:shadow-raised">
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-mist font-display text-base font-semibold text-leaf-700" aria-hidden="true">{{ $loop->iteration }}</span>
                    <{{ $hasHeading ? 'h3' : 'h2' }} class="font-display text-subheading font-semibold text-forest">
                        @if ($item['link'])
                            <a href="{{ $item['link']['href'] }}" @if ($item['link']['external']) rel="noopener" @endif class="rounded-sm after:absolute after:inset-0 hover:text-leaf-700">{{ $item['title'] }}</a>
                        @else
                            {{ $item['title'] }}
                        @endif
                    </{{ $hasHeading ? 'h3' : 'h2' }}>
                    @if ($item['text'])
                        <p class="text-muted">{{ $item['text'] }}</p>
                    @endif
                    @if ($item['link'])
                        <span class="mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold text-leaf-700" aria-hidden="true">
                            {{ $item['link']['label'] }} <x-heroicon-m-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
