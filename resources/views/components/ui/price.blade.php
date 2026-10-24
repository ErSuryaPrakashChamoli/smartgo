@props([
    'amount' => null,
    'unit' => null,
    'from' => true,
    'tax' => '+ GST',
])

{{-- Prices are always set in Inter: the display face (Sora) has no ₹ glyph. --}}
<p {{ $attributes->class(['font-sans tabular-nums']) }}>
    @if ($amount === null)
        <span class="text-sm font-semibold text-forest">Price on request</span>
    @else
        @if ($from)
            <span class="text-sm text-muted">from</span>
        @endif
        <span class="text-xl font-semibold text-forest">{{ \Illuminate\Support\Number::currency($amount, in: 'INR', locale: 'en_IN', precision: 0) }}</span>
        @if ($unit)
            <span class="text-sm text-muted">/ {{ $unit }}</span>
        @endif
        @if ($tax)
            <span class="text-sm text-muted">{{ $tax }}</span>
        @endif
    @endif
</p>
