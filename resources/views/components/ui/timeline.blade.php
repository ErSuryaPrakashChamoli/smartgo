@props(['items' => []])

{{-- $items: list of ['label' => string, 'detail' => ?string, 'state' => 'done'|'current'|'upcoming']. --}}
<ol {{ $attributes->class(['flex flex-col']) }}>
    @foreach ($items as $item)
        <li class="relative flex gap-3 pb-5 last:pb-0" @if ($item['state'] === 'current') aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute top-7 bottom-0 left-3.5 w-px bg-line" aria-hidden="true"></span>
            @endunless
            <span @class([
                'relative flex size-7 shrink-0 items-center justify-center rounded-full',
                'bg-leaf-700 text-white' => $item['state'] === 'done',
                'bg-forest text-paper ring-4 ring-mist' => $item['state'] === 'current',
                'bg-surface text-muted ring-1 ring-control' => $item['state'] === 'upcoming',
            ])>
                @if ($item['state'] === 'done')
                    <x-heroicon-m-check class="size-4" aria-hidden="true" />
                @else
                    <span class="size-2 rounded-full bg-current" aria-hidden="true"></span>
                @endif
            </span>
            <div class="flex flex-col gap-0.5 pt-0.5">
                <p @class(['text-sm font-semibold', 'text-forest' => $item['state'] !== 'upcoming', 'text-muted' => $item['state'] === 'upcoming'])>
                    {{ $item['label'] }}
                    <span class="sr-only">({{ ['done' => 'completed', 'current' => 'current step', 'upcoming' => 'not started'][$item['state']] }})</span>
                </p>
                @if (! empty($item['detail']))
                    <p class="text-sm text-muted">{{ $item['detail'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
