@props(['actions' => null])

{{--
    Sticky actions on small screens (IA §4). With $actions (the published header buttons) the bar shows
    them, main button last; without it the Phase 2 design previews show their sample buttons. Call and
    WhatsApp join once a number and D-10 are confirmed.
--}}
<div {{ $attributes->class(['fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden']) }}>
    @if ($actions === null)
        <div class="grid grid-cols-2 gap-3">
            <x-ui.button variant="secondary" href="#">Enquire</x-ui.button>
            <x-ui.button href="#" icon="calendar-days">Book a tour</x-ui.button>
        </div>
    @else
        <div @class(['grid gap-3', 'grid-cols-2' => count($actions) > 1])>
            @foreach ($actions as $action)
                <x-ui.button :variant="$loop->last ? 'primary' : 'secondary'" :href="$action['href']" :rel="$action['external'] ? 'noopener' : null">{{ $action['label'] }}</x-ui.button>
            @endforeach
        </div>
    @endif
</div>
