{{-- Variables: $segments, $capacity, $caption (passed with View::make()->viewData()). --}}

{{-- Usage of a shared capacity on one date. $segments: list of ['label', 'seats', 'classes']; the remainder is free. --}}
@php($caption ??= null)
@php($free = $capacity - array_sum(array_column($segments, 'seats')))

<div class="flex flex-col gap-3">
    <div class="flex h-6 w-full overflow-hidden rounded-full bg-success-100 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10" role="img" aria-label="{{ collect($segments)->map(fn ($segment) => $segment['seats'].' '.$segment['label'])->push($free.' free')->implode(', ') }} of {{ $capacity }} seats">
        @foreach ($segments as $segment)
            <div class="h-full {{ $segment['classes'] }}" style="width: {{ $capacity > 0 ? round($segment['seats'] / $capacity * 100, 2) : 0 }}%"></div>
        @endforeach
    </div>
    <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
        @foreach ($segments as $segment)
            <div class="flex items-center gap-2">
                <span class="size-3 rounded-sm {{ $segment['classes'] }}" aria-hidden="true"></span>
                <dt class="text-gray-600 dark:text-gray-300">{{ $segment['label'] }}</dt>
                <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $segment['seats'] }}</dd>
            </div>
        @endforeach
        <div class="flex items-center gap-2">
            <span class="size-3 rounded-sm bg-success-100 ring-1 ring-inset ring-success-600/30" aria-hidden="true"></span>
            <dt class="text-gray-600 dark:text-gray-300">Free</dt>
            <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $free }}</dd>
        </div>
        <div class="flex items-center gap-2">
            <dt class="text-gray-600 dark:text-gray-300">Capacity</dt>
            <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $capacity }}</dd>
        </div>
    </dl>
    @if ($caption)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $caption }}</p>
    @endif
</div>
