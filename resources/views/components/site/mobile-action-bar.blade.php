@props(['actions' => null, 'contact' => []])

{{--
    Sticky actions on small screens (IA §4, FR-DISC-6). With $actions (the published header buttons, or
    a page's own, such as a location's enquiry link) the bar shows them, main button last, after Call and
    WhatsApp links for the published phone and WhatsApp numbers in $contact; beside those only the main
    button fits. Without $actions the Phase 2 design previews show their sample buttons. Booking a tour
    joins in Phase 8; taps are not tracked until consent is agreed (OD-100).
--}}
@php
    $quickContact = collect($contact)
        ->whereIn('label', ['Phone', 'WhatsApp'])
        ->map(fn (array $detail): array => $detail['label'] === 'Phone'
            ? ['label' => 'Call', 'href' => $detail['href'], 'icon' => 'phone', 'external' => false]
            : ['label' => 'WhatsApp', 'href' => $detail['href'], 'icon' => 'chat-bubble-oval-left-ellipsis', 'external' => true])
        ->values();
    $buttons = $actions === null ? null : ($quickContact->isNotEmpty() ? array_slice($actions, -1) : $actions);
@endphp
<div {{ $attributes->class(['fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden']) }}>
    @if ($buttons === null)
        <div class="grid grid-cols-2 gap-3">
            <x-ui.button variant="secondary" href="#">Enquire</x-ui.button>
            <x-ui.button href="#" icon="calendar-days">Book a tour</x-ui.button>
        </div>
    @else
        <div class="flex items-stretch gap-2">
            @foreach ($quickContact as $link)
                <a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif
                    class="flex h-11 min-w-16 shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg px-2 text-xs font-semibold text-forest ring-1 ring-control ring-inset hover:bg-mist">
                    <x-dynamic-component :component="'heroicon-m-'.$link['icon']" class="size-4 text-leaf-700" aria-hidden="true" />
                    {{ $link['label'] }}
                </a>
            @endforeach
            @if ($buttons !== [])
                <div @class(['grid flex-1 gap-3', 'grid-cols-2' => count($buttons) > 1])>
                    @foreach ($buttons as $action)
                        <x-ui.button :variant="$loop->last ? 'primary' : 'secondary'" :href="$action['href']" :rel="$action['external'] ? 'noopener' : null">{{ $action['label'] }}</x-ui.button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
