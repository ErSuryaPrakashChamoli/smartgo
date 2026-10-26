@props(['data'])

<div class="container-page py-10 sm:py-14">
    <x-site.cta-band :eyebrow="$data['eyebrow']" :title="$data['heading']">
        {{ $data['text'] }}
        @if ($data['links'] !== [])
            <x-slot:actions>
                @foreach ($data['links'] as $link)
                    <x-ui.button size="lg" :variant="$loop->first ? 'inverse' : 'inverse-outline'" :href="$link['href']" :rel="$link['external'] ? 'noopener' : null">{{ $link['label'] }}</x-ui.button>
                @endforeach
            </x-slot:actions>
        @endif
    </x-site.cta-band>
</div>
