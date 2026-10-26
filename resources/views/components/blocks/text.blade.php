@props(['data'])

<section class="container-page py-10 sm:py-14">
    <div class="flex max-w-3xl flex-col gap-5">
        @if (filled($data['heading']))
            <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
        @endif
        {{-- Sanitised against a strict allowlist by TextBlock::present() (App\Services\Content\RichText). --}}
        <div class="rich-text">{!! $data['body'] !!}</div>
    </div>
</section>
