@props(['code'])

{{-- Branded artwork for a location without a published photo: its code, the SmartGro mark and a faint grid. Shows no building, so it cannot pass for a picture of the property (BR-MED-2). --}}
<div {{ $attributes->class(['overflow-hidden bg-linear-to-br from-forest-soft to-forest']) }} aria-hidden="true">
    <x-home.blueprint-lines class="absolute inset-0 size-full text-leaf-300/15" />
    <span class="absolute top-5 left-5 flex size-11 items-center justify-center rounded-full bg-paper p-1.5 shadow-card">
        <img src="{{ asset('brand/smartgro-mark.png') }}" alt="" width="222" height="222" class="size-full object-contain">
    </span>
    <span class="absolute top-5 right-5 font-editorial text-[clamp(3rem,4.5vw,4.25rem)] leading-none font-semibold whitespace-nowrap text-leaf-300/30">{{ $code }}</span>
</div>
