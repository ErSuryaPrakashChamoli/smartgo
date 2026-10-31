@props(['contact' => []])

{{-- What a visitor sees instead of the enquiry form until a privacy notice is published (EnquiryForm, D8.1); the same on /enquire and in a page's enquiry form section. --}}
<x-ui.alert type="info" title="Online enquiries open soon">
    The enquiry form opens once SmartGro publishes its privacy notice, so you know how your details are used before you send them.
    @if ($contact !== [])
        In the meantime, contact SmartGro using the details below.
    @endif
</x-ui.alert>
