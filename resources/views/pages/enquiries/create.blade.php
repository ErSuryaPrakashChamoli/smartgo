{{-- The enquiry page (IA /enquire, FR-BK-1). The form is shown only once a privacy notice is published (EnquiryForm); until then visitors are pointed to the published contact details. --}}
<x-layouts::site title="Enquire" description="Ask SmartGro about workspaces in Noida. An enquiry does not reserve anything." :canonical="$canonical" :navigation="$navigation">
    <div class="container-page">
        <div class="mx-auto flex max-w-4xl flex-col gap-10 py-10 lg:py-16">
            <div class="flex flex-col gap-4">
                <x-site.breadcrumb :items="[['label' => 'Home', 'href' => '/'], ['label' => 'Enquire']]" />
                <x-ui.section-heading level="h1" eyebrow="Enquire" title="Ask about a workspace">
                    Tell SmartGro what you need. A team member replies with options, prices and availability in writing. An enquiry never reserves anything.
                </x-ui.section-heading>
            </div>

            @if ($isOpen)
                <div class="rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-10">
                    <x-site.enquiry-form :properties="$properties" :categories="$categories" :segments="$segments" :team-sizes="$teamSizes"
                        :selected="$selected" :started="$started" :notice="$notice" :from="$from" :utm="$utm" />
                </div>
            @else
                <x-ui.alert type="info" title="Online enquiries open soon">
                    The enquiry form opens once SmartGro publishes its privacy notice, so you know how your details are used before you send them.
                    @if ($contact !== [])
                        In the meantime, contact SmartGro using the details below.
                    @endif
                </x-ui.alert>
            @endif
        </div>
    </div>

    @unless ($isOpen)
        <x-site.contact-section :contact="$contact" />
    @endunless
</x-layouts::site>
