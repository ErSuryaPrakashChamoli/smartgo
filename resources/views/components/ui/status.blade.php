@props([
    'status',
    'detail' => null,
])

{{--
    Journey statuses. Wording keeps a request apart from a confirmation: only "Tour confirmed" and
    "Booking confirmed" say confirmed (ERD §5 statuses; BR-BK-3, BR-BK-10, BR-TR-3).
--}}
@php
    $statuses = [
        'enquiry-received' => ['label' => 'Enquiry received', 'icon' => 'inbox-arrow-down', 'tone' => 'info'],
        'tour-requested' => ['label' => 'Tour requested: not yet confirmed', 'icon' => 'clock', 'tone' => 'warning'],
        'tour-confirmed' => ['label' => 'Tour confirmed', 'icon' => 'check-circle', 'tone' => 'success'],
        'booking-requested' => ['label' => 'Booking requested: not yet confirmed', 'icon' => 'clock', 'tone' => 'warning'],
        'held' => ['label' => 'Held while you finish: not yet confirmed', 'icon' => 'lock-closed', 'tone' => 'info'],
        'pending-payment' => ['label' => 'Awaiting payment: not yet confirmed', 'icon' => 'credit-card', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Booking confirmed', 'icon' => 'check-badge', 'tone' => 'success'],
        'payment-received' => ['label' => 'Payment received', 'icon' => 'banknotes', 'tone' => 'success'],
        'payment-failed' => ['label' => 'Payment failed: not booked', 'icon' => 'x-circle', 'tone' => 'danger'],
        'slot-taken' => ['label' => 'No longer available: not booked', 'icon' => 'x-circle', 'tone' => 'danger'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'x-mark', 'tone' => 'neutral'],
    ];

    $state = $statuses[$status] ?? throw new InvalidArgumentException("Unknown status [{$status}].");
@endphp

<span {{ $attributes->class(['inline-flex flex-wrap items-center gap-2']) }}>
    <x-ui.badge :tone="$state['tone']" :icon="$state['icon']">{{ $state['label'] }}</x-ui.badge>
    @if ($detail)
        <span class="text-xs text-muted">{{ $detail }}</span>
    @endif
</span>
