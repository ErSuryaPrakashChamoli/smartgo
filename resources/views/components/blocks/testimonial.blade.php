@props(['data'])

@if ($data['testimonial'])
    <section class="container-page py-10 sm:py-14">
        <figure class="flex max-w-3xl flex-col gap-5 rounded-3xl bg-surface p-8 shadow-card ring-1 ring-line sm:p-10">
            <blockquote class="font-display text-heading font-semibold text-pretty text-forest">
                <p>&ldquo;{{ $data['testimonial']['quote'] }}&rdquo;</p>
            </blockquote>
            <figcaption class="flex flex-col text-sm">
                <span class="font-semibold text-ink">{{ $data['testimonial']['person'] }}</span>
                @if (filled($data['testimonial']['role']))
                    <span class="text-muted">{{ $data['testimonial']['role'] }}</span>
                @endif
            </figcaption>
        </figure>
    </section>
@endif
