{{-- Variables: $seats, $states (passed with View::make()->viewData()). --}}

{{--
    Seat map for per-seat and hybrid zones. Every state pairs a colour with an icon and a text
    legend, so state is never shown by colour alone. $seats: list of ['label', 'state'].
--}}
<div class="flex flex-col gap-4">
    <ul class="grid grid-cols-4 gap-2 sm:grid-cols-8" aria-label="Seats">
        @foreach ($seats as $seat)
            @php($state = $states[$seat['state']])
            <li class="flex flex-col items-center gap-1 rounded-lg px-1 py-2 text-xs font-semibold ring-1 ring-inset {{ $state['classes'] }}">
                <x-dynamic-component :component="'heroicon-m-'.$state['icon']" class="size-4" aria-hidden="true" />
                <span>{{ $seat['label'] }}</span>
                <span class="sr-only">{{ $state['label'] }}</span>
            </li>
        @endforeach
    </ul>
    <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600 dark:text-gray-300" aria-label="Legend">
        @foreach ($states as $state)
            <li class="flex items-center gap-1.5">
                <span class="flex size-6 items-center justify-center rounded ring-1 ring-inset {{ $state['classes'] }}">
                    <x-dynamic-component :component="'heroicon-m-'.$state['icon']" class="size-3.5" aria-hidden="true" />
                </span>
                {{ $state['label'] }}
            </li>
        @endforeach
    </ul>
</div>
