@props([
    'name',
    'legend',
    'options' => [],
    'checked' => null,
    'columns' => 2,
])

{{-- $options: value => ['title' => string, 'description' => ?string]. Native radios, so no JavaScript is needed. --}}
<fieldset {{ $attributes->class(['flex flex-col gap-3']) }}>
    <legend class="text-sm font-semibold text-forest">{{ $legend }}</legend>
    <div @class(['grid gap-3', 'sm:grid-cols-2' => $columns === 2, 'sm:grid-cols-3' => $columns === 3])>
        @foreach ($options as $value => $option)
            <label class="relative cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" class="peer sr-only" @checked($checked === $value)>
                <span class="flex h-full flex-col gap-1 rounded-xl bg-surface p-4 ring-1 ring-control ring-inset peer-checked:bg-mist peer-checked:ring-2 peer-checked:ring-leaf-700 peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-leaf-700">
                    <span class="text-sm font-semibold text-forest">{{ $option['title'] }}</span>
                    @if (! empty($option['description']))
                        <span class="text-sm text-muted">{{ $option['description'] }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
