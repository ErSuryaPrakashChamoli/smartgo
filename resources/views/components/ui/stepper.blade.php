@props([
    'steps' => [],
    'current' => 1,
])

{{-- $steps: list of step labels; $current is 1-based. --}}
<nav aria-label="Progress" {{ $attributes }}>
    <p class="text-sm font-semibold text-forest sm:hidden">Step {{ $current }} of {{ count($steps) }}: {{ $steps[$current - 1] ?? '' }}</p>
    <ol class="hidden items-center gap-2 sm:flex">
        @foreach ($steps as $label)
            @php($number = $loop->iteration)
            <li class="flex items-center gap-2" @if ($number === $current) aria-current="step" @endif>
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-leaf-700 text-white' => $number < $current,
                    'bg-forest text-paper' => $number === $current,
                    'bg-surface text-muted ring-1 ring-control' => $number > $current,
                ])>
                    @if ($number < $current)
                        <x-heroicon-m-check class="size-4" aria-hidden="true" />
                        <span class="sr-only">Completed:</span>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class(['text-sm', 'font-semibold text-forest' => $number === $current, 'text-muted' => $number !== $current])>{{ $label }}</span>
                @unless ($loop->last)
                    <span class="h-px w-6 bg-line lg:w-10" aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
