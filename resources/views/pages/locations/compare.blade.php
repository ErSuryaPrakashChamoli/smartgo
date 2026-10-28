{{-- Two or three locations side by side (FR-DISC-4), from published records only; a row appears only when one of them has something for it. Prices and availability come with inventory and approved pricing, so they read "on request". The choice is a plain form that works without JavaScript, and the address can be shared. --}}
@php
    $rows = [];

    if ($compared !== null) {
        $cells = fn (callable $value): array => array_map($value, $compared);
        $detail = fn (array $item, string $label): ?string => collect($item['profile']['gettingHere'] ?? [])->firstWhere('label', $label)['text'] ?? null;

        $rows = array_filter([
            'Area' => $cells(fn (array $item): string => collect([$item['property']->microMarket?->name, $item['property']->city->name])->filter()->join(', ')),
            'Address' => $cells(fn (array $item): string => $item['property']->addressLine()),
            'Opening' => collect($compared)->contains(fn (array $item): bool => $item['property']->openingLabel() !== null) ? $cells(fn (array $item): string => $item['property']->openingLabel() ?? 'Open now') : null,
            'Workspace types' => $cells(fn (array $item): ?string => $item['workspaceTypes'] === [] ? null : implode(', ', $item['workspaceTypes'])),
            'Opening hours' => $cells(fn (array $item): ?array => $item['openingHours'] === [] ? null : $item['openingHours']),
            ...collect(['Nearest metro station', 'Walk from the metro', 'Buses', 'Parking', 'Accessibility'])->mapWithKeys(fn (string $label): array => [$label => $cells(fn (array $item): ?string => $detail($item, $label))])->all(),
            'Prices and availability' => $cells(fn (): string => 'Confirmed on request'),
        ], fn (?array $row): bool => $row !== null && collect($row)->filter()->isNotEmpty());
    }
@endphp
<x-layouts::site title="Compare locations" description="Compare SmartGro locations side by side." :noindex="true" :navigation="$navigation">
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => '/locations'], ['label' => 'Compare']]" />
        <div class="flex max-w-3xl flex-col gap-3">
            <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Compare SmartGro locations</h1>
            <p class="text-lead text-pretty text-muted">Choose two or three locations to see them side by side. SmartGro confirms prices and availability when you enquire.</p>
        </div>

        @if ($missing > 0)
            <x-ui.alert type="warning">{{ $missing === 1 ? 'One location in this link is' : $missing.' locations in this link are' }} no longer on the website, so {{ $missing === 1 ? 'it is' : 'they are' }} left out.</x-ui.alert>
        @endif
        @if ($tooMany)
            <x-ui.alert type="error">Choose up to {{ \App\Http\Controllers\Web\LocationController::MAX_COMPARED }} locations to compare.</x-ui.alert>
        @elseif ($tooFew)
            <x-ui.alert type="info">Choose at least two locations to compare.</x-ui.alert>
        @endif

        @if ($compared !== null)
            <section aria-labelledby="comparison-heading" class="flex flex-col gap-4">
                <h2 id="comparison-heading" class="sr-only">Comparison</h2>
                <div class="relative overflow-x-auto rounded-2xl bg-surface shadow-card ring-1 ring-line" tabindex="0" role="region" aria-labelledby="comparison-heading">
                    <table class="w-full min-w-[40rem] table-fixed text-left text-sm">
                        <caption class="sr-only">SmartGro locations compared</caption>
                        <thead>
                            <tr class="border-b border-line align-top">
                                <td class="w-40 p-4"></td>
                                @foreach ($compared as $item)
                                    <th scope="col" class="p-4">
                                        <span class="flex flex-col items-start gap-1">
                                            <a href="{{ $item['property']->path() }}" class="font-display text-base font-semibold text-forest hover:text-leaf-700">SmartGro {{ $item['property']->name }}</a>
                                            @if ($item['removeHref'])
                                                <a href="{{ $item['removeHref'] }}" class="text-sm font-normal text-leaf-700 hover:underline">Remove<span class="sr-only"> SmartGro {{ $item['property']->name }}</span></a>
                                            @endif
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $label => $row)
                                <tr class="border-b border-line align-top">
                                    <th scope="row" class="p-4 font-semibold text-ink">{{ $label }}</th>
                                    @foreach ($row as $cell)
                                        <td class="p-4 text-ink">
                                            @if (is_array($cell))
                                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                                                    @foreach ($cell as $day)
                                                        <dt class="font-semibold text-forest">{{ $day['day'] }}</dt>
                                                        <dd class="text-muted">{{ $day['hours'] }}</dd>
                                                    @endforeach
                                                </dl>
                                            @elseif ($cell === null)
                                                <span class="text-muted">Not listed</span>
                                            @else
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr class="align-top">
                                <td class="p-4"></td>
                                @foreach ($compared as $item)
                                    <td class="p-4">
                                        <span class="flex flex-col items-start gap-2">
                                            <x-ui.button size="sm" :href="$item['enquiryHref']" icon="chat-bubble-left-right">Enquire<span class="sr-only"> about SmartGro {{ $item['property']->name }}</span></x-ui.button>
                                            <a href="{{ $item['property']->path() }}" class="text-sm font-semibold text-leaf-700 hover:underline">View location<span class="sr-only"> SmartGro {{ $item['property']->name }}</span></a>
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if (count($choices) > 1)
            <form method="get" action="{{ route('locations.compare') }}" class="flex max-w-2xl flex-col gap-5 rounded-2xl bg-surface p-6 ring-1 ring-line">
                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-2 font-display text-subheading font-semibold text-forest">{{ $compared === null ? 'Choose locations' : 'Change the locations' }}</legend>
                    @foreach ($choices as $choice)
                        <x-form.checkbox :id="'compare-'.$loop->index" name="locations[]" :value="$choice['key']" :checked="in_array($choice['key'], $chosenKeys, true)"
                            :label="'SmartGro '.$choice['property']->name.', '.collect([$choice['property']->microMarket?->name, $choice['property']->city->name])->filter()->join(', ')" />
                    @endforeach
                </fieldset>
                <p class="text-sm text-muted">Two or three locations.</p>
                <x-ui.button type="submit" class="self-start" icon="table-cells">Compare</x-ui.button>
            </form>
        @else
            <p class="text-muted">There are not enough locations on the website to compare yet. <a href="/locations" class="font-semibold text-leaf-700 hover:underline">See the locations</a></p>
        @endif
    </div>
</x-layouts::site>
