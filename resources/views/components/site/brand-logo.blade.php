@props([
    'size' => 'md',
    'reversed' => false,
])

{{--
    The only public template that references the logo files, so replacing them is a one-line change
    until brand settings arrive in Phase 4 (FR-BRAND-1). Use the reversed logo on dark backgrounds (OD-45).
--}}
<img
    src="{{ asset($reversed ? 'brand/smartgro-logo-reversed.webp' : 'brand/smartgro-logo.webp') }}"
    alt="SmartGro"
    width="388"
    height="218"
    {{ $attributes->class([
        'w-auto',
        'h-11 sm:h-12' => $size === 'md',
        'h-16 sm:h-20' => $size === 'lg',
    ]) }}
>
