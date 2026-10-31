@php($id = 'interior-'.\Illuminate\Support\Str::random(6))

{{-- An illustration of a workspace interior in brand colours, shown until a photograph of a SmartGro location is published (D-12). Labelled where it is used; never presented as a real space. --}}
<svg viewBox="0 0 960 720" preserveAspectRatio="xMidYMid slice" aria-hidden="true" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $id }}-room" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#123d27" />
            <stop offset="1" stop-color="#0b2c1b" />
        </linearGradient>
        <linearGradient id="{{ $id }}-sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#84bd53" stop-opacity="0.22" />
            <stop offset="1" stop-color="#84bd53" stop-opacity="0.04" />
        </linearGradient>
        <radialGradient id="{{ $id }}-glow">
            <stop offset="0" stop-color="#f7f7f2" stop-opacity="0.28" />
            <stop offset="1" stop-color="#f7f7f2" stop-opacity="0" />
        </radialGradient>
        <clipPath id="{{ $id }}-window">
            <rect x="80" y="70" width="800" height="420" rx="6" />
        </clipPath>
    </defs>

    <rect width="960" height="720" fill="url(#{{ $id }}-room)" />

    {{-- Window wall with a city skyline --}}
    <rect x="80" y="70" width="800" height="420" rx="6" fill="url(#{{ $id }}-sky)" />
    <g clip-path="url(#{{ $id }}-window)" fill="#84bd53">
        <rect x="96" y="300" width="58" height="190" fill-opacity="0.16" />
        <rect x="164" y="210" width="42" height="280" fill-opacity="0.22" />
        <rect x="214" y="330" width="70" height="160" fill-opacity="0.14" />
        <rect x="294" y="170" width="52" height="320" fill-opacity="0.24" />
        <rect x="356" y="260" width="86" height="230" fill-opacity="0.16" />
        <rect x="452" y="130" width="46" height="360" fill-opacity="0.26" />
        <rect x="508" y="290" width="78" height="200" fill-opacity="0.15" />
        <rect x="596" y="200" width="54" height="290" fill-opacity="0.22" />
        <rect x="660" y="250" width="70" height="240" fill-opacity="0.17" />
        <rect x="740" y="160" width="40" height="330" fill-opacity="0.24" />
        <rect x="790" y="310" width="80" height="180" fill-opacity="0.14" />
        <g fill="#f7f7f2" fill-opacity="0.18">
            @foreach ([[176, 230], [176, 270], [176, 310], [306, 190], [306, 240], [306, 290], [464, 150], [464, 200], [464, 250], [464, 300], [608, 220], [608, 270], [752, 180], [752, 230], [752, 280]] as [$x, $y])
                <rect x="{{ $x }}" y="{{ $y }}" width="18" height="10" rx="1" />
            @endforeach
        </g>
    </g>
    <g stroke="#f7f7f2" stroke-opacity="0.22" stroke-width="3" fill="none">
        <rect x="80" y="70" width="800" height="420" rx="6" />
        <path d="M280 70v420M480 70v420M680 70v420M80 170h800" />
    </g>

    {{-- Floor --}}
    <rect x="0" y="490" width="960" height="230" fill="#0b2c1b" />
    <path d="M0 490h960" stroke="#f7f7f2" stroke-opacity="0.2" stroke-width="2" />
    <path d="M480 490 120 720M480 490 840 720M480 490v230" stroke="#f7f7f2" stroke-opacity="0.05" stroke-width="2" />

    {{-- Pendant lights --}}
    @foreach ([330, 480, 630] as $x)
        <circle cx="{{ $x }}" cy="150" r="90" fill="url(#{{ $id }}-glow)" />
        <path d="M{{ $x }} 0v118" stroke="#f7f7f2" stroke-opacity="0.35" stroke-width="2" />
        <path d="M{{ $x - 30 }} 146a30 28 0 0 1 60 0z" fill="#84bd53" fill-opacity="0.75" />
    @endforeach

    {{-- Chairs, table and laptops --}}
    <g fill="#84bd53" fill-opacity="0.28">
        @foreach ([322, 402, 482, 562] as $x)
            <rect x="{{ $x }}" y="500" width="56" height="48" rx="14" />
        @endforeach
    </g>
    <rect x="290" y="548" width="380" height="24" rx="12" fill="#f7f7f2" fill-opacity="0.85" />
    <path d="M330 572v70M630 572v70" stroke="#f7f7f2" stroke-opacity="0.45" stroke-width="6" stroke-linecap="round" />
    <g fill="#123d27">
        <path d="M352 548h52l-6-26h-40z" />
        <path d="M552 548h52l-6-26h-40z" />
    </g>
    <g fill="#84bd53" fill-opacity="0.38">
        @foreach ([342, 442, 542] as $x)
            <rect x="{{ $x }}" y="590" width="56" height="50" rx="14" />
        @endforeach
    </g>

    {{-- Sofa --}}
    <rect x="712" y="560" width="210" height="44" rx="18" fill="#3f9b24" fill-opacity="0.55" />
    <rect x="700" y="590" width="234" height="58" rx="22" fill="#3f9b24" fill-opacity="0.7" />
    <path d="M722 648v20M912 648v20" stroke="#f7f7f2" stroke-opacity="0.4" stroke-width="5" stroke-linecap="round" />

    {{-- Plants --}}
    <rect x="128" y="600" width="78" height="66" rx="10" fill="#f7f7f2" fill-opacity="0.16" />
    <g fill="#3f9b24">
        <ellipse cx="150" cy="560" rx="22" ry="48" transform="rotate(-24 150 560)" fill-opacity="0.75" />
        <ellipse cx="186" cy="552" rx="22" ry="52" transform="rotate(20 186 552)" fill-opacity="0.65" />
        <ellipse cx="168" cy="530" rx="18" ry="54" fill-opacity="0.85" />
    </g>
    <g fill="#84bd53" fill-opacity="0.5">
        <ellipse cx="128" cy="545" rx="12" ry="34" transform="rotate(-40 128 545)" />
        <ellipse cx="208" cy="540" rx="12" ry="34" transform="rotate(40 208 540)" />
    </g>
</svg>
