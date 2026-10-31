@props([
    'tourOffered' => false,
    'enquiryOpen' => false,
])

@php
    $steps = [
        ['icon' => 'squares-2x2', 'title' => 'Choose a workspace', 'text' => 'Compare private offices, desks, meeting rooms, virtual offices and managed offices.'],
        ['icon' => 'map-pin', 'title' => 'Visit a location', 'text' => $tourOffered ? 'Request a tour time online. SmartGro confirms it with you.' : 'Ask SmartGro to arrange a visit to the location you like.'],
        ['icon' => 'chat-bubble-left-right', 'title' => 'Agree the details', 'text' => 'SmartGro shares prices and availability with you directly.'.($tourOffered || $enquiryOpen ? ' A request is not a booking.' : '')],
    ];
@endphp

{{-- How taking a workspace works today, as the website and SmartGro handle it; no step promises what the website cannot do (OD-144). --}}
<section class="py-14 sm:py-20" aria-labelledby="steps-heading">
    <div class="container-page flex flex-col gap-10">
        <div class="flex max-w-2xl flex-col gap-2" data-reveal>
            <h2 id="steps-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">How it works</h2>
            <p class="text-lg text-pretty text-muted">Three steps from looking to working.</p>
        </div>

        <ol class="relative grid gap-8 md:grid-cols-3 md:gap-6" data-reveal-group>
            @foreach ($steps as $step)
                <li class="relative flex flex-col gap-4">
                    <div class="flex items-center gap-4">
                        <span class="relative flex size-14 shrink-0 items-center justify-center rounded-2xl bg-forest text-leaf-300 shadow-card" aria-hidden="true">
                            <x-dynamic-component :component="'heroicon-o-'.$step['icon']" class="size-6" />
                            <span class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-leaf-600 text-xs font-bold text-white ring-4 ring-paper">{{ $loop->iteration }}</span>
                        </span>
                        @unless ($loop->last)
                            <span class="hidden h-px flex-1 bg-linear-to-r from-leaf-500 to-line md:block" aria-hidden="true"></span>
                        @endunless
                    </div>
                    <h3 class="font-display text-subheading font-semibold text-forest">{{ $step['title'] }}</h3>
                    <p class="text-muted">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>

        @if ($tourOffered || $enquiryOpen)
            <div class="flex flex-col gap-3 sm:flex-row" data-reveal>
                @if ($tourOffered)
                    <x-ui.button size="lg" pill href="/book-a-tour" icon="calendar-days">Request a tour</x-ui.button>
                @endif
                @if ($enquiryOpen)
                    <x-ui.button size="lg" pill :variant="$tourOffered ? 'outline' : 'primary'" href="/enquire" icon="arrow-right">Send an enquiry</x-ui.button>
                @endif
            </div>
        @endif
    </div>
</section>
