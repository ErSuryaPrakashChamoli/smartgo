@props(['size' => 'md'])

{{-- The only public template that references the logo file, so replacing it is a one-line change until brand settings arrive in Phase 4 (FR-BRAND-1). --}}
<img
    src="{{ asset('brand/smartgro-logo.webp') }}"
    alt="SmartGro"
    width="388"
    height="218"
    {{ $attributes->class([
        'w-auto',
        'h-11 sm:h-12' => $size === 'md',
        'h-16 sm:h-20' => $size === 'lg',
    ]) }}
>
