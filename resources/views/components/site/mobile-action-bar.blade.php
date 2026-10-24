{{-- Sticky actions on small screens (IA §4). Call and WhatsApp join once a number and D-10 are confirmed. --}}
<div {{ $attributes->class(['fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur lg:hidden']) }}>
    <div class="grid grid-cols-2 gap-3">
        <x-ui.button variant="secondary" href="#">Enquire</x-ui.button>
        <x-ui.button href="#" icon="calendar-days">Book a tour</x-ui.button>
    </div>
</div>
