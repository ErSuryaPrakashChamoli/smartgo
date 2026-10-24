@props(['items' => []])

{{-- $items: list of ['label' => string, 'href' => ?string]; the last item is the current page. --}}
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted">
        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                @if (! $loop->first)
                    <x-heroicon-m-chevron-right class="size-4 text-control" aria-hidden="true" />
                @endif
                @if ($loop->last || empty($item['href']))
                    <span @if ($loop->last) aria-current="page" class="font-medium text-forest" @endif>{{ $item['label'] }}</span>
                @else
                    <a href="{{ $item['href'] }}" class="rounded-sm underline-offset-4 hover:text-leaf-700 hover:underline">{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
