@php($id = 'blueprint-'.\Illuminate\Support\Str::random(6))

{{-- A faint architectural grid with two arcs that echo the logo, for dark panels. Takes its colour from the text colour. --}}
<svg aria-hidden="true" preserveAspectRatio="xMaxYMid slice" viewBox="0 0 1200 600" {{ $attributes }}>
    <defs>
        <pattern id="{{ $id }}" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M40 0H0v40" fill="none" stroke="currentColor" stroke-width="1" />
        </pattern>
    </defs>
    <rect width="1200" height="600" fill="url(#{{ $id }})" />
    <g fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="1180" cy="620" r="360" />
        <circle cx="1180" cy="620" r="250" />
    </g>
</svg>
