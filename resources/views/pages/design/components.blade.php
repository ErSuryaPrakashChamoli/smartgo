<x-layouts::site
    title="Components (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page flex flex-col gap-16 py-12 lg:py-16">
        <div class="flex flex-col gap-4">
            <x-site.breadcrumb :items="[['label' => 'Design', 'href' => route('design.index')], ['label' => 'Components']]" />
            <x-ui.section-heading level="h1" eyebrow="Phase 2 · Step 2.4" title="Component library">
                Every component with its states. Hover and keyboard focus are live: point at or tab to any control.
            </x-ui.section-heading>
        </div>

        {{-- Buttons --}}
        <section class="flex flex-col gap-6" aria-labelledby="buttons">
            <h2 id="buttons" class="font-display text-heading font-semibold text-forest">Buttons</h2>
            <div class="overflow-x-auto rounded-2xl bg-surface ring-1 ring-line">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Variant</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Default</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Disabled</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Loading</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach (['primary' => 'Book a tour', 'secondary' => 'Enquire', 'ghost' => 'View all'] as $variant => $label)
                            <tr>
                                <th scope="row" class="px-5 py-4 font-semibold text-forest capitalize">{{ $variant }}</th>
                                <td class="px-5 py-4"><x-ui.button :variant="$variant">{{ $label }}</x-ui.button></td>
                                <td class="px-5 py-4"><x-ui.button :variant="$variant" disabled>{{ $label }}</x-ui.button></td>
                                <td class="px-5 py-4"><x-ui.button :variant="$variant" loading>{{ $label }}</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div data-surface="dark" class="flex flex-wrap items-center gap-4 rounded-2xl bg-forest p-6">
                <x-ui.button variant="inverse">Inverse</x-ui.button>
                <x-ui.button variant="inverse-outline">Inverse outline</x-ui.button>
                <x-ui.button variant="inverse" disabled>Disabled</x-ui.button>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <x-ui.button size="sm">Small</x-ui.button>
                <x-ui.button>Medium</x-ui.button>
                <x-ui.button size="lg" icon="arrow-right">Large with icon</x-ui.button>
            </div>
        </section>

        {{-- Form controls --}}
        <section class="flex flex-col gap-6" aria-labelledby="forms">
            <h2 id="forms" class="font-display text-heading font-semibold text-forest">Form controls</h2>
            <div class="grid gap-6 rounded-2xl bg-surface p-6 ring-1 ring-line md:grid-cols-2">
                <x-form.field label="Full name" for="demo-name" hint="As it should appear on your proposal." required>
                    <x-form.input id="demo-name" placeholder="Your name" described-by="demo-name-hint" />
                </x-form.field>
                <x-form.field label="Work email" for="demo-email" error="Enter an email address in the format name@company.com." required>
                    <x-form.input id="demo-email" type="email" value="name@company" invalid described-by="demo-email-error" />
                </x-form.field>
                <x-form.field label="Team size" for="demo-size">
                    <x-form.select id="demo-size" placeholder="Choose a range" :options="['1-5' => '1–5 people', '6-20' => '6–20 people', '21-49' => '21–49 people', '50+' => '50 or more']" />
                </x-form.field>
                <x-form.field label="Preferred property" for="demo-property" hint="Unavailable until you choose a workspace type.">
                    <x-form.select id="demo-property" placeholder="Any Noida property" disabled described-by="demo-property-hint" />
                </x-form.field>
                <x-form.field label="Anything else we should know?" for="demo-notes" class="md:col-span-2">
                    <x-form.textarea id="demo-notes" placeholder="Move-in date, parking, accessibility needs…" />
                </x-form.field>
                <div class="flex flex-col gap-4 md:col-span-2">
                    <x-form.checkbox id="demo-consent" label="Contact me about this enquiry" hint="We use your details only to respond to this enquiry." />
                    <x-form.checkbox id="demo-disabled" label="Disabled option" disabled />
                </div>
            </div>
        </section>

        {{-- Status --}}
        <section class="flex flex-col gap-6" aria-labelledby="status">
            <h2 id="status" class="font-display text-heading font-semibold text-forest">Availability and prices</h2>
            <div class="flex flex-wrap gap-3">
                @foreach (['available', 'limited', 'unavailable', 'on-request'] as $status)
                    <x-ui.availability :status="$status" />
                @endforeach
            </div>
            <div class="grid gap-4 rounded-2xl bg-surface p-6 ring-1 ring-line sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-muted">Price with an approved amount (sample value)</p>
                    <x-ui.price :amount="125000" unit="month" />
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-sm font-semibold text-muted">No approved price</p>
                    <x-ui.price />
                </div>
            </div>
        </section>

        {{-- Messages --}}
        <section class="flex flex-col gap-6" aria-labelledby="messages">
            <h2 id="messages" class="font-display text-heading font-semibold text-forest">Messages</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.alert type="info" title="Tour times shown in IST">Times are shown in India Standard Time.</x-ui.alert>
                <x-ui.alert type="success" title="Tour requested">We will confirm your slot by email.</x-ui.alert>
                <x-ui.alert type="warning" title="Few desks left">Only a small number of desks remain for this date.</x-ui.alert>
                <x-ui.alert type="error" title="We could not send your enquiry">Check the highlighted fields and try again.</x-ui.alert>
            </div>
        </section>

        {{-- Cards and their states --}}
        <section class="flex flex-col gap-6" aria-labelledby="cards">
            <h2 id="cards" class="font-display text-heading font-semibold text-forest">Cards: default, loading and empty</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-card.property name="Sample property" area="Sample area" city="Sample city" :workspaces="['Sample type', 'Sample type']" availability="available" :price="125000" price-unit="month" />
                <x-card.property name="A-23" area="Sector 3" city="Noida" />
                <x-ui.skeleton label="Loading properties" class="rounded-2xl bg-surface p-5 ring-1 ring-line" />
                <x-ui.empty-state icon="building-office" title="No properties match">
                    Try another workspace type, or clear the filters.
                    <x-slot:action>
                        <x-ui.button variant="secondary" size="sm">Clear filters</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (array_slice($categories, 0, 4) as $category)
                    <x-card.workspace :name="$category['name']" :icon="$category['icon']">{{ $category['summary'] }}</x-card.workspace>
                @endforeach
            </div>
        </section>

        {{-- Navigation and disclosure --}}
        <section class="flex flex-col gap-6" aria-labelledby="navigation">
            <h2 id="navigation" class="font-display text-heading font-semibold text-forest">Breadcrumb and FAQ</h2>
            <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => '#'], ['label' => 'Noida', 'href' => '#'], ['label' => 'A-70, Sector 2']]" />
            <div class="max-w-3xl">
                <x-ui.faq-item question="An open question (select to expand)" class="border-t" open>
                    Answers sit here. Native disclosure keeps them usable without JavaScript.
                </x-ui.faq-item>
                <x-ui.faq-item question="A closed question">Hidden until opened.</x-ui.faq-item>
            </div>
        </section>

        <x-site.cta-band eyebrow="Call to action" title="Dark section with actions">
            Used once per page at most, for the main conversion.
            <x-slot:actions>
                <x-ui.button variant="inverse">Primary action</x-ui.button>
                <x-ui.button variant="inverse-outline">Secondary action</x-ui.button>
            </x-slot:actions>
        </x-site.cta-band>
    </div>
</x-layouts::site>
