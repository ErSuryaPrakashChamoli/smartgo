@props(['status' => 'on-request'])

{{-- Availability never relies on colour alone: every state pairs an icon with a text label (DESIGN_SYSTEM §3). --}}
@php
    $states = [
        'available' => ['label' => 'Available', 'icon' => 'check-circle', 'classes' => 'bg-available-soft text-available'],
        'limited' => ['label' => 'Limited availability', 'icon' => 'exclamation-triangle', 'classes' => 'bg-limited-soft text-limited'],
        'unavailable' => ['label' => 'Fully occupied', 'icon' => 'x-circle', 'classes' => 'bg-unavailable-soft text-unavailable'],
        'on-request' => ['label' => 'Availability on request', 'icon' => 'chat-bubble-left-ellipsis', 'classes' => 'bg-mist text-forest'],
    ];

    $state = $states[$status] ?? $states['on-request'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', $state['classes']]) }}>
    <x-dynamic-component :component="'heroicon-m-'.$state['icon']" class="size-3.5" aria-hidden="true" />
    {{ $slot->isNotEmpty() ? $slot : $state['label'] }}
</span>
