@props([
    'name',
    'category',
    'capacity' => null,
    'included' => [],
    'notIncluded' => [],
    'availability' => 'on-request',
    'availableFrom' => null,
    'price' => null,
    'priceUnit' => null,
    'action' => 'Enquire',
    'actionHref' => '#',
])

{{-- One workspace offering at a property (IA §3.1 item 3): capacity, inclusions, an explicit "not included" list, availability, price and the category's next step. --}}
<article {{ $attributes->class(['flex flex-col gap-5 rounded-2xl bg-surface p-5 ring-1 ring-line sm:p-6 lg:flex-row lg:items-start lg:gap-8']) }}>
    <div class="flex flex-1 flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="brand">{{ $category }}</x-ui.badge>
            <x-ui.availability :status="$availability" :from="$availableFrom" />
        </div>
        <h3 class="font-display text-subheading font-semibold text-forest">{{ $name }}</h3>
        @if ($capacity)
            <p class="flex items-center gap-1.5 text-sm text-muted">
                <x-heroicon-m-user-group class="size-4 text-leaf-700" aria-hidden="true" />
                {{ $capacity }}
            </p>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            @if ($included !== [])
                <div>
                    <p class="text-sm font-semibold text-forest">Included</p>
                    <ul class="mt-1.5 flex flex-col gap-1 text-sm text-ink">
                        @foreach ($included as $item)
                            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 size-4 shrink-0 text-available" aria-hidden="true" />{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($notIncluded !== [])
                <div>
                    <p class="text-sm font-semibold text-forest">Not included</p>
                    <ul class="mt-1.5 flex flex-col gap-1 text-sm text-ink">
                        @foreach ($notIncluded as $item)
                            <li class="flex gap-2"><x-heroicon-m-minus class="mt-0.5 size-4 shrink-0 text-muted" aria-hidden="true" />{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
    <div class="flex flex-col gap-3 border-t border-line pt-4 lg:w-56 lg:shrink-0 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
        <x-ui.price :amount="$price" :unit="$priceUnit" />
        <x-ui.button :href="$actionHref" class="w-full">{{ $action }}</x-ui.button>
    </div>
</article>
