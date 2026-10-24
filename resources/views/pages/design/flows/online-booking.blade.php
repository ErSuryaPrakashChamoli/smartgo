<x-layouts::site
    title="Journey: online booking (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    @php($steps = ['Choose', 'Your details', 'Pay', 'Done'])

    <div class="container-page">
        <div class="mx-auto flex max-w-5xl flex-col gap-12 py-10 lg:py-16">
            <div class="flex flex-col gap-4">
                <x-site.breadcrumb :items="[['label' => 'Design', 'href' => route('design.index')], ['label' => 'Journey: online booking']]" />
                <x-ui.section-heading level="h1" eyebrow="Journey · online booking" title="Book and pay online">
                    For inventory where SmartGro switches on instant booking. Example: a hot-desk day pass, which draws on a shared pool of desks (pooled capacity).
                </x-ui.section-heading>
                <x-site.design-note title="Not available yet">
                    Instant booking can be enabled for a category only after the online-booking and payment decisions D-07 and D-08 (BR-PRODCFG-5). Until then these products use the request-to-book journey. Desks, times and amounts here are samples on the fictional "Sample property".
                </x-site.design-note>
            </div>

            <x-site.flow-screen number="1" title="Choose">
                <div class="flex flex-col gap-6">
                    <x-ui.stepper :steps="$steps" :current="1" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.field label="Property" for="ob-property"><x-form.select id="ob-property" :options="['sample' => 'Sample property']" /></x-form.field>
                        <x-form.field label="Date" for="ob-date" required><x-form.input id="ob-date" type="date" /></x-form.field>
                        <x-form.field label="Desks" for="ob-desks" required hint="Up to 4 per booking (sample rule)">
                            <x-form.input id="ob-desks" type="number" min="1" max="4" value="2" described-by="ob-desks-hint" />
                        </x-form.field>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-paper p-4 ring-1 ring-line">
                        <x-ui.availability status="limited" />
                        <x-ui.price />
                    </div>
                    <div class="flex justify-end border-t border-line pt-5"><x-ui.button size="lg" icon="arrow-right">Continue</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="2" title="Desks held while you finish" description="A hold blocks the desks for 10 minutes (BR-BK-3, BR-BK-4); it simply stops counting when it expires.">
                <div class="flex flex-col gap-6">
                    <x-ui.stepper :steps="$steps" :current="2" />
                    <div class="flex flex-col gap-3 rounded-xl bg-info-soft p-4 sm:flex-row sm:items-center sm:justify-between">
                        <x-ui.status status="held" />
                        <p class="text-sm font-semibold text-info tabular-nums" role="timer">2 desks held for <span class="font-display text-subheading">09:41</span> more</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.field label="Full name" for="ob-name" required><x-form.input id="ob-name" autocomplete="name" /></x-form.field>
                        <x-form.field label="Work email" for="ob-email" required><x-form.input id="ob-email" type="email" autocomplete="email" /></x-form.field>
                        <x-form.field label="Mobile number" for="ob-phone" required><x-form.input id="ob-phone" type="tel" autocomplete="tel" /></x-form.field>
                        <x-form.field label="GSTIN" for="ob-gstin" hint="Optional; needed for a GST invoice"><x-form.input id="ob-gstin" described-by="ob-gstin-hint" /></x-form.field>
                    </div>
                    <div class="flex justify-between border-t border-line pt-5"><x-ui.button variant="ghost">Back</x-ui.button><x-ui.button size="lg" icon="arrow-right">Continue to payment</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <x-site.flow-screen number="3" title="Pay">
                <div class="flex flex-col gap-6">
                    <x-ui.stepper :steps="$steps" :current="3" />
                    <dl class="grid gap-4 rounded-2xl bg-paper p-5 ring-1 ring-line text-sm sm:grid-cols-3">
                        <div><dt class="text-muted">Day pass</dt><dd class="font-semibold text-forest">2 desks · Sample property</dd></div>
                        <div><dt class="text-muted">Date</dt><dd class="font-semibold text-forest">Your date</dd></div>
                        <div><dt class="text-muted">Amount</dt><dd class="font-semibold text-muted italic">Shown once prices are approved</dd></div>
                    </dl>
                    <x-ui.placeholder icon="credit-card" label="Secure payment by the provider SmartGro chooses (D-07)" class="min-h-40" />
                    <p class="text-sm text-muted">Your booking is confirmed only when the payment succeeds. Cancellation and refund policy: to be confirmed (D-08).</p>
                    <div class="flex justify-between border-t border-line pt-5"><x-ui.button variant="ghost">Back</x-ui.button><x-ui.button size="lg">Pay securely</x-ui.button></div>
                </div>
            </x-site.flow-screen>

            <div class="grid gap-8 lg:grid-cols-2">
                <x-site.flow-screen number="4a" title="Paid and confirmed">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap gap-2"><x-ui.status status="confirmed" /><x-ui.status status="payment-received" /></div>
                        <h3 class="font-display text-subheading font-semibold text-forest">Your day pass is booked</h3>
                        <p class="text-sm text-muted">Reference <span class="font-semibold text-forest tabular-nums">BKG-······</span>. Your receipt and booking details are on their way by email.</p>
                        <div><x-ui.button icon="calendar-days" size="sm">Add to calendar</x-ui.button></div>
                    </div>
                </x-site.flow-screen>

                <x-site.flow-screen number="4b" title="Payment still processing">
                    <div class="flex flex-col gap-4">
                        <x-ui.status status="pending-payment" />
                        <h3 class="font-display text-subheading font-semibold text-forest">We are waiting for your bank</h3>
                        <p class="text-sm text-muted">Your desks stay held until the payment provider answers or the hold ends. We will email you as soon as it is confirmed. Please do not pay twice.</p>
                    </div>
                </x-site.flow-screen>

                <x-site.flow-screen number="4c" title="Payment failed">
                    <div class="flex flex-col gap-4">
                        <x-ui.status status="payment-failed" />
                        <h3 class="font-display text-subheading font-semibold text-forest">The payment did not go through</h3>
                        <p class="text-sm text-muted">Your desks are held until the timer ends, so you can try again. After that they are released.</p>
                        <div><x-ui.button size="sm">Try again</x-ui.button></div>
                    </div>
                </x-site.flow-screen>

                <x-site.flow-screen number="4d" title="Last desk taken" description="Another customer completed a booking first; the server re-checks under lock (BR-BK-5).">
                    <div class="flex flex-col gap-4">
                        <x-ui.status status="slot-taken" />
                        <h3 class="font-display text-subheading font-semibold text-forest">Those desks are no longer available</h3>
                        <p class="text-sm text-muted">No booking was made. If any amount was debited, it is returned under SmartGro's refund policy (D-08).</p>
                        <div><x-ui.button size="sm" variant="secondary">Choose another date</x-ui.button></div>
                    </div>
                </x-site.flow-screen>
            </div>
        </div>
    </div>
</x-layouts::site>
