<x-layouts::site
    title="Enterprise and managed offices (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <section data-surface="dark" class="relative isolate overflow-hidden bg-forest">
        <svg class="absolute inset-0 -z-10 size-full text-white/5" aria-hidden="true">
            <defs>
                <pattern id="enterprise-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                    <path d="M48 0H0v48" fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#enterprise-grid)" />
        </svg>
        <div class="container-page grid gap-10 py-12 lg:grid-cols-12 lg:py-20">
            <div class="flex flex-col gap-6 lg:col-span-7">
                <nav aria-label="Breadcrumb">
                    <ol class="flex items-center gap-1.5 text-sm text-paper/80">
                        <li><a href="{{ route('design.home') }}" class="hover:text-paper hover:underline">Home</a></li>
                        <li class="flex items-center gap-1.5"><x-heroicon-m-chevron-right class="size-4" aria-hidden="true" /><span aria-current="page" class="font-medium text-paper">Enterprise</span></li>
                    </ol>
                </nav>
                <p class="text-eyebrow font-semibold text-leaf-300 uppercase">Enterprise and managed offices</p>
                <h1 class="font-display text-display-xl font-semibold text-balance text-paper">Workspace planned around your company</h1>
                <p class="max-w-xl text-lead text-paper/85">
                    For teams that need a dedicated office suite, several cabins together, or a set-up tailored to how they work. Tell us your requirements and we will prepare options.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button variant="inverse" size="lg" href="#consultation" icon="arrow-right">Request a consultation</x-ui.button>
                    <x-ui.button variant="inverse-outline" size="lg" :href="route('design.flow', 'tour')">Book a tour</x-ui.button>
                </div>
            </div>
            <div class="flex items-center justify-center lg:col-span-5">
                <x-site.brand-logo size="lg" reversed class="sm:h-24" />
            </div>
        </div>
    </section>

    <div class="container-page flex flex-col gap-14 py-10 lg:gap-20 lg:py-16">
        <section aria-labelledby="process-heading" class="flex flex-col gap-8">
            <x-ui.section-heading eyebrow="How it works" title="From requirement to move-in" />
            <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['chat-bubble-left-right', 'Consultation', 'We understand your seats, locations, timeline and set-up needs.'],
                    ['map-pin', 'Site visits', 'You visit the properties and spaces that fit.'],
                    ['document-text', 'Proposal', 'You receive a written proposal for the agreed scope.'],
                    ['clipboard-document-list', 'Agreement', 'Terms are agreed and the space is reserved.'],
                    ['key', 'Set-up and move-in', 'The space is prepared to the agreed plan.'],
                ] as [$icon, $title, $text])
                    <li class="flex flex-col gap-3 rounded-2xl bg-surface p-5 ring-1 ring-line">
                        <span class="font-display text-heading font-semibold text-leaf-600">0{{ $loop->iteration }}</span>
                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6 text-leaf-700" aria-hidden="true" />
                        <h3 class="font-semibold text-forest">{{ $title }}</h3>
                        <p class="text-sm text-muted">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section id="consultation" aria-labelledby="consultation-heading" class="grid scroll-mt-28 gap-8 rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-10 lg:grid-cols-12">
            <div class="flex flex-col gap-4 lg:col-span-4">
                <x-ui.section-heading eyebrow="Consultation" title="Tell us what you need" level="h2">
                    A SmartGro team member will contact you to discuss it. Sending this form does not reserve any space.
                </x-ui.section-heading>
            </div>
            <form action="#" class="flex flex-col gap-6 lg:col-span-8">
                <x-form.radio-cards name="seats" legend="How many seats do you need?" :columns="2" :options="[
                    '20-49' => ['title' => '20–49 seats'],
                    '50-99' => ['title' => '50–99 seats'],
                    '100-249' => ['title' => '100–249 seats'],
                    '250+' => ['title' => '250 seats or more'],
                ]" />
                <fieldset class="flex flex-col gap-3">
                    <legend class="text-sm font-semibold text-forest">Where do you need space?</legend>
                    <p class="-mt-1 text-sm text-muted">SmartGro currently operates in Noida. Tell us about other locations too.</p>
                    <x-form.checkbox id="enterprise-noida" label="Noida" />
                    <x-form.checkbox id="enterprise-other" label="Another Delhi NCR location (add it in the notes)" />
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field label="When do you need to move in?" for="enterprise-timeline">
                        <x-form.select id="enterprise-timeline" placeholder="Choose a timeframe" :options="['0-3' => 'Within 3 months', '3-6' => 'In 3–6 months', '6-12' => 'In 6–12 months', 'exploring' => 'Just exploring']" />
                    </x-form.field>
                    <x-form.field label="Set-up" for="enterprise-fitout">
                        <x-form.select id="enterprise-fitout" placeholder="Choose one" :options="['ready' => 'Ready to move in', 'custom' => 'Customised set-up', 'unsure' => 'Not sure yet']" />
                    </x-form.field>
                    <x-form.field label="Company" for="enterprise-company" required>
                        <x-form.input id="enterprise-company" autocomplete="organization" />
                    </x-form.field>
                    <x-form.field label="Full name" for="enterprise-name" required>
                        <x-form.input id="enterprise-name" autocomplete="name" />
                    </x-form.field>
                    <x-form.field label="Work email" for="enterprise-email" required>
                        <x-form.input id="enterprise-email" type="email" autocomplete="email" />
                    </x-form.field>
                    <x-form.field label="Mobile number" for="enterprise-phone" required>
                        <x-form.input id="enterprise-phone" type="tel" autocomplete="tel" />
                    </x-form.field>
                </div>
                <x-form.field label="Anything else we should know?" for="enterprise-notes">
                    <x-form.textarea id="enterprise-notes" placeholder="Other locations, team structure, security or IT needs…" />
                </x-form.field>
                <x-form.checkbox id="enterprise-consent" label="Contact me about this request" hint="Privacy notice to be added (D-15)." />
                <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted">You will get a reference by email. This is a request, not a booking.</p>
                    <x-ui.button type="submit" size="lg">Request a consultation</x-ui.button>
                </div>
            </form>
        </section>

        <section aria-labelledby="stories-heading" class="flex flex-col gap-6">
            <x-ui.section-heading eyebrow="Customer stories" title="Teams working with SmartGro" />
            <x-ui.empty-state icon="document-text" title="Case studies appear once SmartGro publishes verified client stories">
                Only real, consented stories are shown. No client names, logos or results are added before then.
            </x-ui.empty-state>
        </section>

        <section aria-labelledby="enterprise-faqs" class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="FAQs" title="Enterprise questions" />
            </div>
            <div class="lg:col-span-8">
                @foreach (['Can the office be set up to our brand and layout?', 'How long are managed office agreements?', 'Can we combine several spaces or properties?'] as $question)
                    <x-ui.faq-item :question="$question" :class="$loop->first ? 'border-t' : ''">Answer to be supplied by SmartGro.</x-ui.faq-item>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts::site>
