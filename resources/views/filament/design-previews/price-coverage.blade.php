{{-- Variables: $properties (list of names), $products (list of names), $cells (product => property => state), $states (state => label, icon, classes). --}}

{{-- Price coverage: one cell per property and product. "Not set" (missing) never looks like "Free (₹0)". --}}
<div class="flex flex-col gap-4">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th scope="col" class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">Product</th>
                    @foreach ($properties as $property)
                        <th scope="col" class="px-2 py-2 font-semibold text-gray-950 dark:text-white">{{ $property }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($products as $product)
                    <tr>
                        <th scope="row" class="py-2 pr-4 font-medium text-gray-700 dark:text-gray-200">{{ $product }}</th>
                        @foreach ($properties as $property)
                            @php($state = $states[$cells[$product][$property]])
                            <td class="px-2 py-2">
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $state['classes'] }}">
                                    <x-dynamic-component :component="'heroicon-m-'.$state['icon']" class="size-3.5" aria-hidden="true" />
                                    {{ $state['label'] }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <ul class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600 dark:text-gray-300" aria-label="Price states">
        @foreach ($states as $state)
            <li class="flex items-center gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 font-semibold ring-1 ring-inset {{ $state['classes'] }}">
                    <x-dynamic-component :component="'heroicon-m-'.$state['icon']" class="size-3.5" aria-hidden="true" />
                    {{ $state['label'] }}
                </span>
                {{ $state['meaning'] }}
            </li>
        @endforeach
    </ul>
</div>
