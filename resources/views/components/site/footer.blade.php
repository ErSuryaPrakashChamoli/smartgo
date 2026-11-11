@props([
    'categories' => [],
    'properties' => [],
    'navigation' => null,
])

@if ($navigation !== null)
    @php
        $columns = $navigation['footer'];
        $isVisitDetail = fn (array $detail): bool => in_array($detail['label'], ['Address', 'Opening hours'], true);
        $contactDetails = array_values(array_filter($navigation['contact'] ?? [], fn (array $detail): bool => ! $isVisitDetail($detail)));
        $visitDetails = array_values(array_filter($navigation['contact'] ?? [], $isVisitDetail));
        $address = collect($visitDetails)->firstWhere('label', 'Address');
        $directionsUrl = $address === null ? null : 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode(trim(preg_replace('/\s+/', ' ', $address['text'])));
        $contactPath = App\Services\Website\SiteSections::SECTIONS['contact']['path'];
        $gridColumns = match (count($columns) + ($contactDetails === [] ? 0 : 1)) {
            0 => 'lg:grid-cols-1',
            1 => 'lg:grid-cols-[1.2fr_repeat(1,1fr)]',
            2 => 'lg:grid-cols-[1.2fr_repeat(2,1fr)]',
            3 => 'lg:grid-cols-[1.2fr_repeat(3,1fr)]',
            default => 'lg:grid-cols-[1.2fr_repeat(4,1fr)]',
        };
    @endphp

    @php($contactIcons = ['Phone' => 'phone', 'WhatsApp' => 'chat-bubble-oval-left-ellipsis', 'Email' => 'envelope', 'Address' => 'map-pin', 'Opening hours' => 'clock'])

    {{-- The published footer columns and legal links on Deep Forest, with the dark-background logo (OD-45); nothing is shown for a menu that is not published. The address and opening hours sit in a "Visit our office" card under the columns, beside the map (OD-180). --}}
    <footer {{ $attributes->class(['bg-forest text-paper']) }} data-surface="dark">
        <div @class(['container-page grid gap-10 pt-14 sm:pt-16', $visitDetails === [] ? 'pb-14 sm:pb-16' : 'pb-10', $gridColumns])>
            <div class="flex flex-col gap-5">
                <x-site.brand-logo size="lg" reversed class="self-start" />
                <p class="max-w-xs text-sm text-paper/80">Coworking and managed workspaces in Noida, Delhi NCR.</p>
                @if (($navigation['social'] ?? []) !== [])
                    <ul aria-label="SmartGro on social networks" class="flex flex-wrap gap-2">
                        @foreach ($navigation['social'] as $link)
                            <li>
                                <a href="{{ $link['href'] }}" rel="me noopener" title="{{ $link['label'] }}" class="inline-flex size-8 items-center justify-center rounded-full text-paper ring-1 ring-white/25 hover:bg-white/10 hover:text-leaf-300">
                                    <x-site.social-icon :network="$link['network']" class="size-4" />
                                    <span class="sr-only">{{ $link['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @foreach ($columns as $column)
                <nav aria-label="{{ $column['label'] }}" class="flex flex-col gap-3">
                    <h2 class="text-sm font-semibold text-paper">{{ $column['label'] }}</h2>
                    <ul class="flex flex-col gap-2.5 text-sm">
                        @foreach ($column['children'] as $link)
                            <li><a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif class="text-paper/80 hover:text-leaf-300">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
            @if ($contactDetails !== [])
                <div class="flex flex-col gap-3">
                    <h2 class="text-sm font-semibold text-paper">Contact</h2>
                    <dl class="flex flex-col gap-2.5 text-sm">
                        @foreach ($contactDetails as $detail)
                            <div>
                                <dt class="sr-only">{{ $detail['label'] }}</dt>
                                <dd class="flex items-start gap-2.5 text-paper/80">
                                    <x-dynamic-component :component="'heroicon-m-'.($contactIcons[$detail['label']] ?? 'information-circle')" class="mt-0.5 size-4 shrink-0 text-leaf-300" aria-hidden="true" />
                                    @if ($detail['href'])
                                        <a href="{{ $detail['href'] }}" class="hover:text-leaf-300">{{ $detail['label'] === 'WhatsApp' ? 'WhatsApp '.$detail['text'] : $detail['text'] }}</a>
                                    @else
                                        <span class="whitespace-pre-line">{{ $detail['text'] }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
        @if ($visitDetails !== [])
            <div class="container-page pb-14 sm:pb-16">
                <div class="grid overflow-hidden rounded-3xl bg-linear-to-br from-forest-soft to-forest ring-1 ring-white/10 lg:grid-cols-5">
                    <div @class(['relative isolate flex min-w-0 flex-col justify-center gap-5 overflow-hidden p-6 sm:p-8 lg:p-10', $address === null ? 'lg:col-span-5' : 'lg:col-span-2'])>
                        <div class="pointer-events-none absolute -bottom-24 -left-20 -z-10 size-56 rounded-full bg-leaf-500/20 blur-3xl" aria-hidden="true"></div>
                        <div>
                            <h2 class="text-eyebrow font-semibold text-leaf-300 uppercase">Visit our office</h2>
                            <p class="mt-2 font-display text-xl font-semibold text-paper sm:text-2xl">SmartGro</p>
                        </div>
                        <dl class="flex flex-col gap-3 text-sm">
                            @foreach ($visitDetails as $detail)
                                <div>
                                    <dt class="sr-only">{{ $detail['label'] }}</dt>
                                    <dd class="flex items-start gap-3 text-paper/80">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-leaf-300">
                                            <x-dynamic-component :component="'heroicon-m-'.$contactIcons[$detail['label']]" class="size-4" aria-hidden="true" />
                                        </span>
                                        <span class="pt-1.5 whitespace-pre-line">{{ $detail['text'] }}</span>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                        <div class="flex flex-wrap gap-3">
                            @if ($directionsUrl !== null)
                                <x-ui.button :href="$directionsUrl" variant="inverse" size="sm" :pill="true" icon="arrow-right" rel="noopener">Get directions</x-ui.button>
                            @endif
                            <x-ui.button :href="$contactPath" variant="inverse-outline" size="sm" :pill="true">Contact us</x-ui.button>
                        </div>
                    </div>
                    @if ($address !== null)
                        <x-site.google-map :address="$address['text']" class="h-64 border-t border-white/10 sm:h-72 lg:col-span-3 lg:h-auto lg:min-h-80 lg:border-t-0 lg:border-l" />
                    @endif
                </div>
            </div>
        @endif
        <div class="border-t border-white/10">
            <div class="container-page flex flex-col gap-3 py-6 text-sm text-paper/75 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} SmartGro · Work smart. Grow together.</p>
                @if ($navigation['legal'] !== [])
                    <nav aria-label="Legal">
                        <ul class="flex flex-wrap gap-x-5 gap-y-2">
                            @foreach ($navigation['legal'] as $link)
                                <li><a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif class="hover:text-leaf-300">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </footer>
@else

{{-- Light footer: the logo has a dark wordmark, and no reversed version exists yet (D-11b). --}}
<footer {{ $attributes->class(['border-t border-line bg-surface']) }}>
    <div class="container-page grid gap-10 py-14 lg:grid-cols-[1.2fr_repeat(3,1fr)]">
        <div class="flex flex-col gap-4">
            <x-site.brand-logo size="lg" class="self-start" />
            <p class="max-w-xs text-sm text-muted">Coworking and managed workspaces in Noida, Delhi NCR.</p>
        </div>
        <nav aria-label="Workspaces" class="flex flex-col gap-3">
            <p class="text-sm font-semibold text-forest">Workspaces</p>
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($categories as $category)
                    <li><a href="#" class="text-muted hover:text-leaf-700">{{ $category['name'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <nav aria-label="Locations" class="flex flex-col gap-3">
            <p class="text-sm font-semibold text-forest">Locations in Noida</p>
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($properties as $property)
                    <li><a href="#" class="text-muted hover:text-leaf-700">{{ $property['name'] }}, {{ $property['area'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <nav aria-label="Company" class="flex flex-col gap-3">
            <p class="text-sm font-semibold text-forest">Company</p>
            <ul class="flex flex-col gap-2 text-sm">
                @foreach (['Enterprise', 'Insights', 'About', 'Contact', 'FAQs'] as $item)
                    <li><a href="#" class="text-muted hover:text-leaf-700">{{ $item }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>
    <div class="border-t border-line">
        <div class="container-page flex flex-col gap-3 py-6 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} SmartGro</p>
            <ul class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach (['Privacy', 'Terms', 'Cookies'] as $item)
                    <li><a href="#" class="hover:text-leaf-700">{{ $item }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>
@endif
