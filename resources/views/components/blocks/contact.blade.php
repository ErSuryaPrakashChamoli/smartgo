@props(['data'])

{{-- SmartGro's main published contact details, then, if chosen, the locations on the website with their addresses. --}}
<x-site.contact-section :contact="$data['contact']" :title="$data['heading']" :intro="$data['intro']" />
@if ($data['locations'] !== [])
    <section class="container-page flex flex-col gap-6 py-10 sm:py-14" aria-labelledby="contact-locations-heading">
        <h2 id="contact-locations-heading" class="font-display text-display-lg font-semibold text-balance text-forest">Our locations</h2>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($data['locations'] as $location)
                <li class="flex flex-col gap-1 rounded-2xl bg-surface p-5 shadow-card ring-1 ring-line">
                    <a href="{{ $location['path'] }}" class="font-display text-subheading font-semibold text-forest hover:text-leaf-700">{{ $location['name'] }}</a>
                    <p class="text-sm text-muted">{{ $location['address'] }}</p>
                </li>
            @endforeach
        </ul>
    </section>
@endif
