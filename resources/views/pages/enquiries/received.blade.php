{{-- After an enquiry is sent (the approved "Enquiry received" screen): the reference only, never the visitor's details, and a clear statement that nothing is reserved. --}}
<x-layouts::site title="Enquiry received" :noindex="true" :navigation="$navigation">
    <div class="container-page">
        <div class="mx-auto grid max-w-5xl gap-10 py-10 lg:grid-cols-2 lg:py-16">
            <div class="flex flex-col gap-4">
                <x-ui.status status="enquiry-received" />
                <h1 class="font-display text-display-lg font-semibold text-forest">Thank you, we have your enquiry</h1>
                @if ($reference)
                    <p class="text-muted">Your reference is <span class="font-semibold text-forest tabular-nums">{{ $reference }}</span>. A SmartGro team member will contact you.</p>
                @else
                    <p class="text-muted">A SmartGro team member will contact you.</p>
                @endif
                <x-ui.alert type="info" title="This is not a booking">Nothing is reserved, booked or charged. Prices, availability and terms are confirmed in writing.</x-ui.alert>
                <x-ui.button variant="secondary" href="/locations" icon="map-pin" class="self-start">See SmartGro locations</x-ui.button>
            </div>
            <x-ui.timeline :items="[
                ['label' => 'Enquiry received', 'detail' => 'Just now', 'state' => 'done'],
                ['label' => 'SmartGro contacts you', 'detail' => 'By email or phone, as you asked', 'state' => 'current'],
                ['label' => 'Visit or proposal', 'detail' => 'Options, prices and availability in writing', 'state' => 'upcoming'],
            ]" />
        </div>
    </div>
</x-layouts::site>
