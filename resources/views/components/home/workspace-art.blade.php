@props(['kind' => null])

@php($id = 'workspace-art-'.\Illuminate\Support\Str::random(6))

{{--
    Artwork for a family of workspace types without a published photo: a top-down pictogram in the style
    of the floor-plan illustration (private offices, desks, meeting rooms, a virtual office's mail, a
    managed floor). An illustration, never presented as a SmartGro space (D-12).
--}}
<svg viewBox="0 0 400 225" preserveAspectRatio="xMidYMid slice" aria-hidden="true" {{ $attributes }}>
    <defs>
        <pattern id="{{ $id }}" width="20" height="20" patternUnits="userSpaceOnUse">
            <path d="M20 0H0v20" fill="none" stroke="#84bd53" stroke-opacity="0.1" stroke-width="1" />
        </pattern>
    </defs>
    <rect width="400" height="225" fill="#123d27" />
    <rect width="400" height="225" fill="url(#{{ $id }})" />

    <g stroke="#f7f7f2" stroke-opacity="0.45" stroke-width="2.5" fill="none" stroke-linecap="round">
        @switch($kind)
            @case('private-offices')
                @foreach ([70, 160, 250] as $x)
                    <rect x="{{ $x }}" y="45" width="80" height="120" rx="4" />
                    <path d="M{{ $x + 52 }} 165a28 28 0 0 0-28-28" stroke-dasharray="4 4" />
                @endforeach
                @break
            @case('desks')
                <path d="M60 112h280" stroke-dasharray="6 6" stroke-opacity="0.25" />
                @break
            @case('meeting-rooms')
                <rect x="70" y="35" width="260" height="155" rx="6" stroke="#84bd53" stroke-opacity="0.75" stroke-dasharray="8 6" />
                <path d="M150 50h100" stroke-width="5" stroke-opacity="0.6" />
                @break
            @case('virtual-office')
                <rect x="95" y="62" width="150" height="100" rx="8" />
                <path d="m95 70 75 52 75-52" />
                @break
            @default
                <rect x="60" y="35" width="280" height="155" rx="6" />
                <path d="M60 112h280M150 35v77M250 35v77M200 112v78" />
        @endswitch
    </g>

    @switch($kind)
        @case('private-offices')
            <g fill="#f7f7f2" fill-opacity="0.2">
                @foreach ([70, 160, 250] as $x)
                    <rect x="{{ $x + 14 }}" y="60" width="52" height="24" rx="3" />
                @endforeach
            </g>
            <g fill="#84bd53" fill-opacity="0.55">
                @foreach ([70, 160, 250] as $x)
                    <circle cx="{{ $x + 40 }}" cy="98" r="9" />
                @endforeach
            </g>
            @break
        @case('desks')
            <g fill="#f7f7f2" fill-opacity="0.2">
                @foreach ([65, 120, 175, 230, 285] as $x)
                    <rect x="{{ $x }}" y="70" width="48" height="30" rx="3" />
                    <rect x="{{ $x }}" y="124" width="48" height="30" rx="3" />
                @endforeach
            </g>
            <g fill="#84bd53" fill-opacity="0.55">
                @foreach ([65, 120, 175, 230, 285] as $x)
                    <circle cx="{{ $x + 24 }}" cy="58" r="8" />
                    <circle cx="{{ $x + 24 }}" cy="166" r="8" />
                @endforeach
            </g>
            @break
        @case('meeting-rooms')
            <rect x="120" y="88" width="160" height="54" rx="27" fill="#f7f7f2" fill-opacity="0.22" />
            <g fill="#84bd53" fill-opacity="0.6">
                @foreach ([145, 185, 225, 255] as $x)
                    <circle cx="{{ $x }}" cy="74" r="9" />
                    <circle cx="{{ $x }}" cy="156" r="9" />
                @endforeach
                <circle cx="104" cy="115" r="9" />
                <circle cx="296" cy="115" r="9" />
            </g>
            @break
        @case('virtual-office')
            <path d="M285 50c-22 0-38 16-38 37 0 28 38 68 38 68s38-40 38-68c0-21-16-37-38-37z" fill="#3f9b24" fill-opacity="0.7" />
            <circle cx="285" cy="86" r="13" fill="#f7f7f2" fill-opacity="0.85" />
            <path d="M110 180h120" stroke="#84bd53" stroke-opacity="0.5" stroke-width="4" stroke-linecap="round" />
            @break
        @default
            <g fill="#f7f7f2" fill-opacity="0.18">
                @foreach ([[75, 50], [165, 50], [265, 50]] as [$x, $y])
                    <rect x="{{ $x }}" y="{{ $y }}" width="56" height="26" rx="3" />
                @endforeach
                @foreach ([80, 130, 230, 280] as $x)
                    <rect x="{{ $x }}" y="140" width="40" height="22" rx="3" />
                @endforeach
            </g>
            <g fill="#84bd53" fill-opacity="0.55">
                <circle cx="103" cy="92" r="8" />
                <circle cx="193" cy="92" r="8" />
                <circle cx="293" cy="92" r="8" />
                <circle cx="100" cy="176" r="7" />
                <circle cx="250" cy="176" r="7" />
            </g>
    @endswitch
</svg>
