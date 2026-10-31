@php($id = 'plan-'.\Illuminate\Support\Str::random(6))

{{-- An illustrated floor plan with typical zones, shown where 3D walkthroughs and floor plans will be offered. Not a SmartGro floor plan: always captioned as an illustration (OD-118 d, D-12). --}}
<svg viewBox="0 0 720 520" preserveAspectRatio="xMidYMid meet" aria-hidden="true" {{ $attributes }}>
    <defs>
        <pattern id="{{ $id }}-grid" width="24" height="24" patternUnits="userSpaceOnUse">
            <path d="M24 0H0v24" fill="none" stroke="#84bd53" stroke-opacity="0.09" stroke-width="1" />
        </pattern>
    </defs>

    <rect width="720" height="520" fill="#123d27" />
    <rect width="720" height="520" fill="url(#{{ $id }}-grid)" />

    {{-- Outer walls --}}
    <rect x="70" y="60" width="580" height="400" rx="10" fill="#0b2c1b" fill-opacity="0.75" stroke="#f7f7f2" stroke-opacity="0.45" stroke-width="4" />

    <g fill="none" stroke="#f7f7f2" stroke-opacity="0.3" stroke-width="2">
        {{-- Private offices along the top wall --}}
        <path d="M70 180h290M142 60v120M215 60v120M288 60v120M360 60v120" />
        {{-- Lounge and café, bottom left --}}
        <path d="M70 340h190v120" />
        {{-- Reception, bottom right --}}
        <path d="M520 460v-80h130" />
    </g>
    {{-- Glass meeting room --}}
    <rect x="390" y="60" width="150" height="140" fill="#84bd53" fill-opacity="0.08" stroke="#84bd53" stroke-opacity="0.7" stroke-width="2" stroke-dasharray="8 6" />
    <rect x="425" y="105" width="80" height="50" rx="8" fill="#f7f7f2" fill-opacity="0.18" />

    {{-- Coworking desks --}}
    <g fill="#f7f7f2" fill-opacity="0.16">
        @foreach ([240, 290] as $y)
            @foreach ([300, 352, 404, 456] as $x)
                <rect x="{{ $x }}" y="{{ $y }}" width="44" height="30" rx="4" />
            @endforeach
        @endforeach
    </g>
    <g fill="#84bd53" fill-opacity="0.35">
        @foreach ([300, 352, 404, 456] as $x)
            <circle cx="{{ $x + 22 }}" cy="230" r="7" />
            <circle cx="{{ $x + 22 }}" cy="330" r="7" />
        @endforeach
    </g>

    {{-- Lounge seating and plants --}}
    <rect x="96" y="372" width="120" height="34" rx="12" fill="#3f9b24" fill-opacity="0.55" />
    <circle cx="232" cy="420" r="14" fill="#f7f7f2" fill-opacity="0.16" />
    @foreach ([[600, 100], [600, 300], [95, 300]] as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="15" fill="#3f9b24" fill-opacity="0.7" />
        <circle cx="{{ $x }}" cy="{{ $y }}" r="7" fill="#84bd53" fill-opacity="0.8" />
    @endforeach
    <rect x="552" y="404" width="80" height="22" rx="6" fill="#f7f7f2" fill-opacity="0.2" />

    {{-- Labels and hotspots --}}
    <g font-family="inherit" font-size="15" font-weight="600" fill="#f7f7f2" fill-opacity="0.85" text-anchor="middle">
        <text x="215" y="210">Private offices</text>
        <text x="465" y="186">Meeting room</text>
        <text x="390" y="378">Coworking</text>
        <text x="150" y="446">Lounge &amp; café</text>
        <text x="585" y="452">Reception</text>
    </g>
    @foreach ([[215, 120], [465, 130], [390, 280], [156, 389], [592, 405]] as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="18" fill="#84bd53" fill-opacity="0.22" />
        <circle cx="{{ $x }}" cy="{{ $y }}" r="9" fill="#3f9b24" stroke="#f7f7f2" stroke-width="3" />
    @endforeach
</svg>
