@props([
    'number',
    'title',
    'description' => null,
])

{{-- One screen of a journey design board: a framed view with its step number and purpose. --}}
<section {{ $attributes->class(['flex flex-col gap-3']) }} aria-label="Screen {{ $number }}: {{ $title }}">
    <div class="flex items-baseline gap-3">
        <span class="font-display text-subheading font-semibold text-leaf-600">{{ $number }}</span>
        <div class="flex flex-col">
            <h2 class="font-display text-subheading font-semibold text-forest">{{ $title }}</h2>
            @if ($description)
                <p class="text-sm text-muted">{{ $description }}</p>
            @endif
        </div>
    </div>
    <div class="rounded-3xl bg-surface p-5 shadow-card ring-1 ring-line sm:p-8">
        {{ $slot }}
    </div>
</section>
