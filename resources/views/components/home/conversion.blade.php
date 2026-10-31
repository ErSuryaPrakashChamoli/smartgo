@props([
    'contact' => [],
    'enquiryOpen' => false,
    'tourOffered' => false,
    'hasLocations' => false,
    'hasFinder' => false,
])

@php
    $icons = ['Phone' => 'phone', 'WhatsApp' => 'chat-bubble-oval-left-ellipsis', 'Email' => 'envelope', 'Address' => 'map-pin', 'Opening hours' => 'clock'];
@endphp

{{-- The closing call to action and SmartGro's published contact details (BR-CMS-9); nothing is invented while none are published. --}}
<section id="contact" class="scroll-mt-24 py-14 sm:py-20" aria-labelledby="contact-heading">
    <div class="container-page grid gap-5 lg:grid-cols-12">
        <div class="flex flex-col justify-between gap-8 rounded-4xl bg-mist px-5 py-8 sm:p-10 lg:col-span-7 lg:p-12" data-reveal>
            <div class="flex flex-col gap-4">
                <h2 id="contact-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Ready for your next workspace?</h2>
                <p class="max-w-xl text-lead text-pretty text-muted">Tell us what kind of workspace your team needs and which location suits you, and SmartGro will help you plan a visit.</p>
            </div>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    @if ($enquiryOpen)
                        <x-ui.button size="lg" pill href="/enquire" icon="arrow-right">Send an enquiry</x-ui.button>
                    @endif
                    @if ($tourOffered)
                        <x-ui.button size="lg" pill :variant="$enquiryOpen ? 'outline' : 'primary'" href="/book-a-tour" icon="calendar-days">Request a tour</x-ui.button>
                    @endif
                    @if (! $enquiryOpen && ! $tourOffered && $hasFinder)
                        <x-ui.button size="lg" pill href="#find" icon="arrow-right">Find your workspace</x-ui.button>
                    @endif
                </div>
                @if ($enquiryOpen || $tourOffered)
                    <p class="flex gap-2 text-sm text-muted">
                        <x-heroicon-m-information-circle class="mt-0.5 size-4 shrink-0 text-leaf-700" aria-hidden="true" />
                        An enquiry or a tour request is not a booking. SmartGro confirms the details with you.
                    </p>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-5 rounded-4xl bg-forest px-5 py-8 text-paper sm:p-10 lg:col-span-5" data-surface="dark">
            <h3 class="font-editorial text-2xl font-semibold">Talk to SmartGro</h3>
            @if ($contact !== [])
                <dl class="flex flex-col divide-y divide-white/10">
                    @foreach ($contact as $detail)
                        <div class="min-w-0 py-4 pl-14 first:pt-0 last:pb-0">
                            <dt class="relative text-sm text-paper/75">
                                <span class="absolute top-0 -left-14 flex size-10 items-center justify-center rounded-full bg-white/10 text-leaf-300" aria-hidden="true">
                                    <x-dynamic-component :component="'heroicon-o-'.($icons[$detail['label']] ?? 'information-circle')" class="size-5" />
                                </span>
                                {{ $detail['label'] }}
                            </dt>
                            <dd class="mt-0.5 font-semibold break-words">
                                @if ($detail['href'])
                                    <a href="{{ $detail['href'] }}" class="hover:text-leaf-300">{{ $detail['text'] }}</a>
                                @else
                                    <span class="whitespace-pre-line">{{ $detail['text'] }}</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <div class="flex flex-col gap-3">
                    <p class="font-semibold">Contact details are on their way</p>
                    <p class="text-paper/80">SmartGro's phone number and email will appear here once they are published.{{ $hasLocations ? ' In the meantime, see where our workspaces are.' : '' }}</p>
                    @if ($hasLocations)
                        <div><x-ui.button pill variant="inverse" href="/locations" icon="map-pin">See our locations</x-ui.button></div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
