<x-layouts::site
    title="Journey: tour (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page">
        <div class="mx-auto flex max-w-5xl flex-col gap-12 py-10 lg:py-16">
            <div class="flex flex-col gap-4">
                <x-site.breadcrumb :items="[['label' => 'Design', 'href' => route('design.index')], ['label' => 'Journey: tour']]" />
                <x-ui.section-heading level="h1" eyebrow="Journey · tour request" title="Visit a property">
                    A tour is requested first and confirmed by SmartGro. The time is claimed under the same lock-and-recheck rule as bookings, so two visitors cannot take the last place on a tour (BR-TR-2).
                </x-ui.section-heading>
            </div>

            <x-site.flow-screen number="1" title="Request a tour" description="Today's state: no tour hours are configured yet, so visitors give a preferred date and part of the day.">
                <x-site.tour-request-form :properties="$properties" selected="a-70" id-prefix="flow-tour" />
            </x-site.flow-screen>

            <x-site.flow-screen number="1b" title="Once tour hours are configured" description="Times come from each property's tour rules (BR-TR-1). Shown with the loading and fully booked states.">
                <div class="grid gap-8 lg:grid-cols-2">
                    <x-ui.slot-picker name="flow-tour-slot" legend="Choose a time" caption="Sample times for layout only: real times come from each property's tour hours." :slots="[
                        ['value' => '1', 'label' => '10:00'], ['value' => '2', 'label' => '10:30', 'disabled' => true], ['value' => '3', 'label' => '11:00', 'checked' => true], ['value' => '4', 'label' => '11:30'],
                        ['value' => '5', 'label' => '14:00'], ['value' => '6', 'label' => '14:30'], ['value' => '7', 'label' => '15:00', 'disabled' => true], ['value' => '8', 'label' => '15:30'],
                    ]" />
                    <div class="flex flex-col gap-6">
                        <x-ui.slot-picker name="flow-tour-loading" legend="Loading" state="loading" />
                        <x-ui.slot-picker name="flow-tour-empty" legend="Fully booked date" state="empty" />
                    </div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="2" title="Tour requested" description="The visitor sees clearly that the tour is not confirmed yet.">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="flex flex-col gap-4">
                        <x-ui.status status="tour-requested" />
                        <h3 class="font-display text-heading font-semibold text-forest">We have your tour request</h3>
                        <dl class="grid grid-cols-2 gap-4 rounded-2xl bg-paper p-4 ring-1 ring-line text-sm">
                            <div><dt class="text-muted">Property</dt><dd class="font-semibold text-forest">A-70, Sector 2</dd></div>
                            <div><dt class="text-muted">Preferred</dt><dd class="font-semibold text-forest">Your date · Morning</dd></div>
                        </dl>
                        <x-ui.alert type="warning" title="Not confirmed yet">A SmartGro team member will confirm the exact time with you by phone or email.</x-ui.alert>
                    </div>
                    <x-ui.timeline :items="[
                        ['label' => 'Request sent', 'detail' => 'Just now', 'state' => 'done'],
                        ['label' => 'SmartGro confirms a time', 'detail' => 'By phone or email', 'state' => 'current'],
                        ['label' => 'Your visit', 'detail' => 'About 30 minutes (default to be confirmed, D-28)', 'state' => 'upcoming'],
                    ]" />
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="3" title="Tour confirmed" description="Sent once a team member confirms; also shown on the visitor's tour page.">
                <div class="flex flex-col gap-5">
                    <x-ui.status status="tour-confirmed" />
                    <h3 class="font-display text-heading font-semibold text-forest">Your tour of A-70 is confirmed</h3>
                    <dl class="grid gap-4 rounded-2xl bg-paper p-4 ring-1 ring-line text-sm sm:grid-cols-3">
                        <div><dt class="text-muted">When</dt><dd class="font-semibold text-forest">Date and time as confirmed</dd></div>
                        <div><dt class="text-muted">Where</dt><dd class="font-semibold text-forest">A-70, Sector 2, Noida</dd></div>
                        <div><dt class="text-muted">Who you will meet</dt><dd class="font-semibold text-forest">A SmartGro team member</dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button icon="calendar-days">Add to calendar</x-ui.button>
                        <x-ui.button variant="secondary" icon="map-pin">Get directions</x-ui.button>
                        <x-ui.button variant="ghost">Reschedule</x-ui.button>
                        <x-ui.button variant="ghost">Cancel tour</x-ui.button>
                    </div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="4" title="If the time was just taken" description="Shown when another visitor claimed the last place while this request was being sent.">
                <div class="flex flex-col gap-5">
                    <x-ui.alert type="error" title="That time is no longer available">Someone booked the last place on that tour a moment ago. Your request was not sent. Please choose another time.</x-ui.alert>
                    <x-ui.slot-picker name="flow-tour-retry" legend="Other times" caption="Sample times for layout only." :slots="[
                        ['value' => '1', 'label' => '11:30'], ['value' => '2', 'label' => '14:00'], ['value' => '3', 'label' => '14:30'], ['value' => '4', 'label' => '15:30'],
                    ]" />
                </div>
            </x-site.flow-screen>
        </div>
    </div>
</x-layouts::site>
