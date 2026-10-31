{{-- A visitor's own tour, reached only through its signed link: status, place and time (no personal details), and, while it is still to come, moving or cancelling it. A confirmed tour can be added to a calendar. Never indexed. --}}
@php
    use App\Enums\Booking\TourStatus;
    $status = match ($tour->status) {
        TourStatus::Requested => 'tour-requested',
        TourStatus::Confirmed => 'tour-confirmed',
        default => 'cancelled',
    };
@endphp
<x-layouts::site :title="'Tour '.$tour->reference" :noindex="true" :navigation="$navigation">
    <div class="container-page">
        <div class="mx-auto flex max-w-3xl flex-col gap-8 py-10 lg:py-16">
            <div class="flex flex-col gap-4">
                @if ($tour->status === TourStatus::Completed || $tour->status === TourStatus::NoShow)
                    <x-ui.badge tone="neutral" icon="check">{{ $tour->status === TourStatus::Completed ? 'Tour completed' : 'Tour time has passed' }}</x-ui.badge>
                @else
                    <x-ui.status :status="$status" />
                @endif
                <h1 class="font-display text-display-lg font-semibold text-forest">Your tour of SmartGro {{ $tour->property->name }}</h1>
                @if (session('tour_changed'))
                    <x-ui.alert type="success" :title="session('tour_changed')" />
                @endif
                @if ($errors->any())
                    <x-ui.alert type="error" title="Not changed">{{ $errors->first() }}</x-ui.alert>
                @endif
                <dl class="grid gap-4 rounded-2xl bg-paper p-4 text-sm ring-1 ring-line sm:grid-cols-3">
                    <div><dt class="text-muted">Reference</dt><dd class="font-semibold text-forest tabular-nums">{{ $tour->reference }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-muted">When</dt><dd class="font-semibold text-forest">{{ $tour->whenLabel() }}</dd></div>
                    <div class="sm:col-span-3"><dt class="text-muted">Where</dt><dd class="font-semibold text-forest">{{ $tour->property->addressLine() }}</dd></div>
                </dl>
                @if ($tour->status === TourStatus::Requested)
                    <x-ui.alert type="warning" title="Not confirmed yet">A SmartGro team member will confirm the time with you by phone or email.</x-ui.alert>
                @endif
                @if ($tour->status === TourStatus::Confirmed && $canChange)
                    <x-ui.button :href="$links['calendar']" icon="calendar-days" class="self-start">Add to calendar</x-ui.button>
                @endif
            </div>

            @if ($canChange)
                <section aria-labelledby="move-heading" class="flex flex-col gap-4 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                    <h2 id="move-heading" class="font-display text-heading font-semibold text-forest">Choose another time</h2>
                    @if ($days === [])
                        <p class="text-sm text-muted">No other tour times are free in the next {{ config('tours.window_days') }} days.</p>
                    @else
                        <ul class="flex flex-wrap gap-2" aria-label="Days with tour times">
                            @foreach ($days as $option)
                                <li>
                                    <a href="{{ $option['href'] }}#move-heading"
                                        @if ($option['date'] === $day) aria-current="date" @endif
                                        @class(['flex h-11 items-center rounded-lg px-3 text-sm font-semibold tabular-nums ring-1 ring-inset focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-leaf-700',
                                            'bg-leaf-700 text-white ring-leaf-700' => $option['date'] === $day,
                                            'bg-surface text-forest ring-control hover:ring-leaf-700' => $option['date'] !== $day && $option['free'] > 0,
                                            'bg-paper text-muted ring-line' => $option['date'] !== $day && $option['free'] === 0])>
                                        {{ $option['label'] }}<span class="sr-only">{{ $option['free'] === 0 ? ', fully booked' : '' }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if ($day !== null)
                            <form method="POST" action="{{ $links['move'] }}" class="flex flex-col gap-4">
                                @csrf
                                <x-ui.slot-picker name="starts_at" :legend="'Times on '.\Carbon\CarbonImmutable::parse($day)->format('l j F').' (India time)'"
                                    :state="collect($times)->where('disabled', false)->isEmpty() ? 'empty' : 'ready'" :slots="$times" />
                                <x-ui.button type="submit" variant="secondary" class="self-start">Move my tour</x-ui.button>
                            </form>
                        @endif
                    @endif
                </section>

                <section aria-labelledby="cancel-heading" class="flex flex-col gap-3 rounded-2xl bg-surface p-6 ring-1 ring-line">
                    <h2 id="cancel-heading" class="font-display text-heading font-semibold text-forest">Cancel the tour</h2>
                    <p class="text-sm text-muted">If you can no longer come, cancel it so the time is free for someone else.</p>
                    <form method="POST" action="{{ $links['cancel'] }}">
                        @csrf
                        <x-ui.button type="submit" variant="ghost">Cancel my tour</x-ui.button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-layouts::site>
