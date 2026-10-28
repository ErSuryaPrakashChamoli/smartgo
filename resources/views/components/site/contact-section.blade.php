@props(['contact' => [], 'title' => 'Talk to SmartGro', 'intro' => null])

{{-- Published contact details only (BR-CMS-9); nothing is invented while none are published. --}}
<section id="contact" {{ $attributes->class(['scroll-mt-24 bg-forest py-16 text-paper sm:py-20']) }}>
    <div class="container-page grid gap-10 lg:grid-cols-12 lg:items-start">
        <div class="flex flex-col gap-4 lg:col-span-6">
            <p class="text-eyebrow font-semibold text-leaf-300 uppercase">Get in touch</p>
            <h2 class="font-display text-display-lg font-semibold text-balance">{{ $title }}</h2>
            <p class="max-w-xl text-lead text-pretty text-paper/85">{{ $intro ?? 'Tell us what kind of workspace your team needs and which location suits you, and SmartGro will help you plan a visit.' }}</p>
        </div>
        <div class="lg:col-span-6">
            @if ($contact !== [])
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ($contact as $detail)
                        <div class="flex flex-col gap-1 rounded-2xl bg-white/5 p-5 ring-1 ring-white/15">
                            <dt class="text-sm text-paper/70">{{ $detail['label'] }}</dt>
                            <dd class="font-semibold break-words whitespace-pre-line">
                                @if ($detail['href'])
                                    <a href="{{ $detail['href'] }}" class="hover:text-leaf-300">{{ $detail['text'] }}</a>
                                @else
                                    {{ $detail['text'] }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <div class="flex flex-col gap-3 rounded-2xl bg-white/5 p-6 ring-1 ring-white/15">
                    <p class="font-semibold">Contact details are on their way</p>
                    <p class="text-paper/80">SmartGro's phone number and email will appear here once they are published. In the meantime, see where our workspaces are.</p>
                    <div><x-ui.button variant="inverse" href="/locations" icon="map-pin">See our locations</x-ui.button></div>
                </div>
            @endif
        </div>
    </div>
</section>
