<x-layouts::site
    title="Journey: request to book (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    @php($steps = ['Room and time', 'Your details', 'Review', 'Request sent'])

    <div class="container-page">
        <div class="mx-auto flex max-w-5xl flex-col gap-12 py-10 lg:py-16">
            <div class="flex flex-col gap-4">
                <x-site.breadcrumb :items="[['label' => 'Design', 'href' => route('design.index')], ['label' => 'Journey: request to book']]" />
                <x-ui.section-heading level="h1" eyebrow="Journey · request to book" title="Request a meeting room">
                    The default for meeting rooms, conference and training rooms and hot-desk day passes until online payment is approved (BR-BK-10). The customer sends a request; SmartGro confirms it, and confirmation re-checks availability under lock (BR-BK-5).
                </x-ui.section-heading>
                <x-site.design-note title="Sample data">
                    Rooms, capacities and times on this page belong to the fictional "Sample property", because no real rooms have been entered yet.
                </x-site.design-note>
            </div>

            <x-site.flow-screen number="1" title="Choose a room and time">
                <div class="flex flex-col gap-6">
                    <x-ui.stepper :steps="$steps" :current="1" />
                    <x-form.radio-cards name="rtb-room" legend="Room" checked="room-8" :options="[
                        'room-8' => ['title' => 'Sample meeting room', 'description' => 'Up to 8 people · Sample property'],
                        'room-24' => ['title' => 'Sample training room', 'description' => 'Up to 24 people · Sample property'],
                    ]" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.field label="Date" for="rtb-date" required><x-form.input id="rtb-date" type="date" /></x-form.field>
                        <x-form.field label="Duration" for="rtb-duration" required>
                            <x-form.select id="rtb-duration" :options="['60' => '1 hour', '120' => '2 hours', '180' => '3 hours']" />
                        </x-form.field>
                        <x-form.field label="Attendees" for="rtb-attendees" required hint="Up to 8 for this room">
                            <x-form.input id="rtb-attendees" type="number" min="1" max="8" value="4" described-by="rtb-attendees-hint" />
                        </x-form.field>
                    </div>
                    <x-ui.slot-picker name="rtb-time" legend="Start time" caption="Sample times for layout only: real times follow the room's hours, notice and turnaround rules (BR-BK-1, BR-BK-7)." :slots="[
                        ['value' => '1', 'label' => '09:00'], ['value' => '2', 'label' => '10:00', 'disabled' => true], ['value' => '3', 'label' => '11:00', 'checked' => true], ['value' => '4', 'label' => '12:00'],
                        ['value' => '5', 'label' => '14:00'], ['value' => '6', 'label' => '15:00'], ['value' => '7', 'label' => '16:00', 'disabled' => true], ['value' => '8', 'label' => '17:00'],
                    ]" />
                    <div class="flex justify-end border-t border-line pt-5"><x-ui.button size="lg" icon="arrow-right">Continue</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="2" title="Your details" description="Fields follow the category's required information (DATABASE_ERD §4.1).">
                <div class="flex flex-col gap-6">
                    <x-ui.stepper :steps="$steps" :current="2" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.field label="Full name" for="rtb-name" required><x-form.input id="rtb-name" autocomplete="name" /></x-form.field>
                        <x-form.field label="Work email" for="rtb-email" required><x-form.input id="rtb-email" type="email" autocomplete="email" /></x-form.field>
                        <x-form.field label="Mobile number" for="rtb-phone" required><x-form.input id="rtb-phone" type="tel" autocomplete="tel" /></x-form.field>
                        <x-form.field label="Company" for="rtb-company"><x-form.input id="rtb-company" autocomplete="organization" /></x-form.field>
                        <x-form.field label="GSTIN" for="rtb-gstin" hint="Optional; needed for a GST invoice">
                            <x-form.input id="rtb-gstin" described-by="rtb-gstin-hint" />
                        </x-form.field>
                    </div>
                    <div class="flex justify-between border-t border-line pt-5"><x-ui.button variant="ghost">Back</x-ui.button><x-ui.button size="lg" icon="arrow-right">Review</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="3" title="Review and send">
                <div class="flex flex-col gap-6">
                    <x-ui.stepper :steps="$steps" :current="3" />
                    <dl class="grid gap-4 rounded-2xl bg-paper p-5 ring-1 ring-line text-sm sm:grid-cols-2">
                        <div><dt class="text-muted">Room</dt><dd class="font-semibold text-forest">Sample meeting room · Sample property</dd></div>
                        <div><dt class="text-muted">When</dt><dd class="font-semibold text-forest">Your date · 11:00–12:00</dd></div>
                        <div><dt class="text-muted">Attendees</dt><dd class="font-semibold text-forest">4</dd></div>
                        <div><dt class="text-muted">Price</dt><dd><x-ui.price /></dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted">Cancellation</dt><dd class="font-semibold text-forest">Policy to be confirmed by SmartGro (D-08)</dd></div>
                    </dl>
                    <x-ui.alert type="info" title="This is a request">The room is not reserved until SmartGro confirms. You will not be charged by sending it.</x-ui.alert>
                    <div class="flex justify-between border-t border-line pt-5"><x-ui.button variant="ghost">Back</x-ui.button><x-ui.button size="lg">Send booking request</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="4" title="Request sent">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="flex flex-col gap-4">
                        <x-ui.stepper :steps="$steps" :current="4" />
                        <x-ui.status status="booking-requested" />
                        <h3 class="font-display text-heading font-semibold text-forest">Your request is with SmartGro</h3>
                        <p class="text-muted">Reference <span class="font-semibold text-forest tabular-nums">BKG-······</span>. We will confirm availability and the price. Until then, the room is not reserved for you.</p>
                    </div>
                    <x-ui.timeline :items="[
                        ['label' => 'Request sent', 'detail' => 'Just now', 'state' => 'done'],
                        ['label' => 'SmartGro checks availability and price', 'state' => 'current'],
                        ['label' => 'Booking confirmed', 'state' => 'upcoming'],
                        ['label' => 'Payment, if this booking needs it', 'detail' => 'Payment rules to be confirmed (D-08)', 'state' => 'upcoming'],
                    ]" />
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="5a" title="Confirmed by SmartGro">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-3">
                        <x-ui.status status="confirmed" />
                        <x-ui.badge tone="neutral" icon="credit-card">Payment: SmartGro will send details</x-ui.badge>
                    </div>
                    <h3 class="font-display text-heading font-semibold text-forest">Your meeting room is booked</h3>
                    <p class="text-muted">Sample meeting room · your date · 11:00–12:00. The confirmation and any payment details are sent by email.</p>
                    <div class="flex flex-wrap gap-3"><x-ui.button icon="calendar-days">Add to calendar</x-ui.button><x-ui.button variant="ghost">Manage booking</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="5b" title="Could not be confirmed" description="When the time was taken before SmartGro confirmed the request.">
                <div class="flex flex-col gap-4">
                    <x-ui.status status="slot-taken" />
                    <h3 class="font-display text-heading font-semibold text-forest">That time is no longer available</h3>
                    <p class="text-muted">The room was booked for that time before we could confirm your request. You have not been charged. Choose another time, or reply to our email and we will suggest one.</p>
                    <div><x-ui.button icon="arrow-right">Choose another time</x-ui.button></div>
                </div>
            </x-site.flow-screen>
        </div>
    </div>
</x-layouts::site>
