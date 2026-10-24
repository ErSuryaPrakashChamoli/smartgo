<x-layouts::site
    title="Journey: enquiry (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page">
    <div class="mx-auto flex max-w-5xl flex-col gap-12 py-10 lg:py-16">
        <div class="flex flex-col gap-4">
            <x-site.breadcrumb :items="[['label' => 'Design', 'href' => route('design.index')], ['label' => 'Journey: enquiry']]" />
            <x-ui.section-heading level="h1" eyebrow="Journey · enquiry first" title="Ask about a workspace">
                Used for private cabins, dedicated desks, virtual offices, managed offices and any general question. An enquiry never reserves anything.
            </x-ui.section-heading>
        </div>

        <x-site.flow-screen number="1" title="Enquiry form" description="Opened from a property, workspace type or the header; the context is carried in.">
            <form action="#" class="flex flex-col gap-6">
                <div class="flex flex-wrap items-center gap-2 rounded-xl bg-paper p-3 ring-1 ring-line">
                    <span class="text-sm text-muted">You are asking about</span>
                    <x-ui.badge tone="brand" icon="building-office">Private cabins</x-ui.badge>
                    <x-ui.badge tone="brand" icon="map-pin">Any Noida property</x-ui.badge>
                    <a href="#" class="ml-auto text-sm font-semibold text-leaf-700 hover:underline">Change</a>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field label="Full name" for="enquiry-name" required><x-form.input id="enquiry-name" autocomplete="name" /></x-form.field>
                    <x-form.field label="Work email" for="enquiry-email" required><x-form.input id="enquiry-email" type="email" autocomplete="email" /></x-form.field>
                    <x-form.field label="Mobile number" for="enquiry-phone" required><x-form.input id="enquiry-phone" type="tel" autocomplete="tel" /></x-form.field>
                    <x-form.field label="Company" for="enquiry-company"><x-form.input id="enquiry-company" autocomplete="organization" /></x-form.field>
                    <x-form.field label="Team size" for="enquiry-size">
                        <x-form.select id="enquiry-size" placeholder="Choose a range" :options="['1' => 'Just me', '2-5' => '2–5 people', '6-20' => '6–20 people', '21-49' => '21–49 people', '50+' => '50 or more']" />
                    </x-form.field>
                    <x-form.field label="Which best describes you?" for="enquiry-segment" hint="Optional. Helps us route your enquiry.">
                        <x-form.select id="enquiry-segment" placeholder="Choose one" :options="collect($segments)->mapWithKeys(fn ($segment) => [$segment => $segment])->all()" described-by="enquiry-segment-hint" />
                    </x-form.field>
                </div>
                <x-form.field label="Your message" for="enquiry-message"><x-form.textarea id="enquiry-message" placeholder="Move-in date, number of seats, anything else…" /></x-form.field>
                <x-form.checkbox id="enquiry-consent" label="Contact me about this enquiry" hint="Privacy notice to be added (D-15)." />
                <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted">This sends an enquiry. Nothing is reserved.</p>
                    <x-ui.button type="submit" size="lg">Send enquiry</x-ui.button>
                </div>
            </form>
        </x-site.flow-screen>

        <x-site.flow-screen number="1a" title="If something needs fixing" description="Errors are listed at the top and next to each field.">
            <div class="flex flex-col gap-5">
                <x-ui.alert type="error" title="Please check 1 field">Enter a work email address in the format name@company.com.</x-ui.alert>
                <x-form.field label="Work email" for="enquiry-email-error" error="Enter an email address in the format name@company.com." required>
                    <x-form.input id="enquiry-email-error" type="email" value="name@company" invalid described-by="enquiry-email-error-error" />
                </x-form.field>
            </div>
        </x-site.flow-screen>

        <x-site.flow-screen number="2" title="Enquiry received" description="Shown on screen and sent by email (subject to consent).">
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="flex flex-col gap-4">
                    <x-ui.status status="enquiry-received" />
                    <h3 class="font-display text-heading font-semibold text-forest">Thank you, we have your enquiry</h3>
                    <p class="text-muted">Your reference is <span class="font-semibold text-forest tabular-nums">ENQ-······</span> (format set in Phase 8). A SmartGro team member will contact you.</p>
                    <x-ui.alert type="info" title="This is not a booking">Nothing is reserved yet. Prices, availability and terms are confirmed in writing.</x-ui.alert>
                </div>
                <x-ui.timeline :items="[
                    ['label' => 'Enquiry received', 'detail' => 'Just now', 'state' => 'done'],
                    ['label' => 'SmartGro contacts you', 'detail' => 'Response time to be confirmed (D-09)', 'state' => 'current'],
                    ['label' => 'Visit or proposal', 'detail' => 'Book a tour or receive options in writing', 'state' => 'upcoming'],
                ]" />
            </div>
        </x-site.flow-screen>
    </div>
    </div>
</x-layouts::site>
