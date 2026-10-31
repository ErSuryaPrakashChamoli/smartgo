{{-- After a tour request is sent (the approved "Tour requested" screen): the reference, place and time only, never the visitor's details, the private link to see or change the tour, and a clear statement that it is not confirmed yet. --}}
<x-layouts::site title="Tour requested" :noindex="true" :navigation="$navigation">
    <div class="container-page">
        <div class="mx-auto grid max-w-5xl gap-10 py-10 lg:grid-cols-2 lg:py-16">
            <div class="flex flex-col gap-4">
                @if ($tour === null || $tour->status === \App\Enums\Booking\TourStatus::Requested)
                    <x-ui.status status="tour-requested" />
                    <h1 class="font-display text-display-lg font-semibold text-forest">We have your tour request</h1>
                @else
                    <x-ui.status status="tour-confirmed" />
                    <h1 class="font-display text-display-lg font-semibold text-forest">Your tour is confirmed</h1>
                @endif
                @if ($tour !== null)
                    <dl class="grid gap-4 rounded-2xl bg-paper p-4 text-sm ring-1 ring-line sm:grid-cols-2">
                        <div><dt class="text-muted">Reference</dt><dd class="font-semibold text-forest tabular-nums">{{ $tour->reference }}</dd></div>
                        <div><dt class="text-muted">Location</dt><dd class="font-semibold text-forest">SmartGro {{ $tour->property->name }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted">When</dt><dd class="font-semibold text-forest">{{ $tour->whenLabel() }}</dd></div>
                    </dl>
                    @if ($tour->status === \App\Enums\Booking\TourStatus::Requested)
                        <x-ui.alert type="warning" title="Not confirmed yet">A SmartGro team member will confirm the time with you by phone or email.</x-ui.alert>
                    @endif
                    <div class="flex flex-col gap-2 rounded-2xl bg-surface p-4 ring-1 ring-line">
                        <p class="text-sm text-muted">Keep this private link to see your tour, move it or cancel it. Anyone with the link can do so.</p>
                        <x-ui.button variant="secondary" :href="$manageUrl" icon="calendar-days" class="self-start">Your tour page</x-ui.button>
                    </div>
                @else
                    <p class="text-muted">A SmartGro team member will contact you.</p>
                @endif
            </div>
            <x-ui.timeline :items="[
                ['label' => 'Request sent', 'detail' => 'Just now', 'state' => 'done'],
                ['label' => 'SmartGro confirms a time', 'detail' => 'By phone or email', 'state' => 'current'],
                ['label' => 'Your visit', 'detail' => 'A tour does not reserve a workspace', 'state' => 'upcoming'],
            ]" />
        </div>
    </div>
</x-layouts::site>
