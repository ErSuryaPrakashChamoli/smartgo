@props(['data', 'preview' => false])

{{--
    The enquiry form as a page section (EnquiryFormBlock): the /enquire form when it is open, otherwise
    the same notice and SmartGro's published contact details. In a staff preview the form is shown as it
    will appear but cannot be sent, so checking a draft never creates an enquiry.
--}}
<section class="container-page py-10 sm:py-14" aria-labelledby="enquiry-section-heading">
    <div class="mx-auto flex max-w-4xl flex-col gap-8">
        <div class="flex flex-col gap-3">
            <h2 id="enquiry-section-heading" class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
            @if (filled($data['intro']))
                <p class="text-lead text-pretty text-muted">{{ $data['intro'] }}</p>
            @endif
        </div>

        @if ($data['isOpen'])
            @if ($preview)
                <x-ui.alert type="info" title="Preview">The form is shown as it will appear on the website; it cannot be sent from this preview.</x-ui.alert>
            @endif
            <div class="rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-10" @if ($preview) inert @endif>
                <x-site.enquiry-form id="enquiry-form" :properties="$data['properties']" :categories="$data['categories']" :segments="$data['segments']"
                    :team-sizes="$data['teamSizes']" :selected="$data['selected']" :started="$data['started']" :notice="$data['notice']" :from="$data['from']" :utm="$data['utm']" return-to-form />
            </div>
        @else
            <x-site.enquiry-closed-notice :contact="$data['contact']" />
        @endif
    </div>
</section>
@unless ($data['isOpen'])
    <x-site.contact-section :contact="$data['contact']" />
@endunless
