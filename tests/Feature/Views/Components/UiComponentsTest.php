<?php

use Illuminate\View\ViewException;

it('formats an approved price in rupees with Indian digit grouping', function () {
    $this->blade('<x-ui.price :amount="125000" unit="month" />')
        ->assertSeeInOrder(['from', '₹1,25,000', '/ month', '+ GST']);
});

it('shows "Price on request" when no price is approved', function () {
    $this->blade('<x-ui.price />')
        ->assertSee('Price on request')
        ->assertDontSee('₹');
});

it('gives every customer-facing availability state its BR-AV-1 text label, not colour alone', function (string $status, string $label) {
    $this->blade('<x-ui.availability :status="$status" from="1 Dec" />', ['status' => $status])->assertSee($label);
})->with([
    'available now' => ['available', 'Available now'],
    'available from a date' => ['available-from', 'Available from 1 Dec'],
    'limited' => ['limited', 'Limited availability'],
    'waitlist' => ['waitlist', 'Join waitlist'],
    'on request' => ['on-request', 'Availability on request'],
]);

it('says "not yet confirmed" for every status where nothing is confirmed', function (string $status) {
    $this->blade('<x-ui.status :status="$status" />', ['status' => $status])->assertSee('not yet confirmed');
})->with(['tour-requested', 'booking-requested', 'held', 'pending-payment']);

it('only uses the word "confirmed" on its own for confirmed tours and bookings', function (string $status, string $label) {
    $this->blade('<x-ui.status :status="$status" />', ['status' => $status])->assertSee($label);
})->with([
    'tour confirmed' => ['tour-confirmed', 'Tour confirmed'],
    'booking confirmed' => ['confirmed', 'Booking confirmed'],
]);

it('rejects an unknown journey status instead of guessing a label', function () {
    $this->blade('<x-ui.status status="maybe-booked" />');
})->throws(ViewException::class, 'Unknown status [maybe-booked].');

it('marks the current step of a journey for screen readers', function () {
    $this->blade('<x-ui.stepper :steps="[\'Choose\', \'Details\', \'Pay\']" :current="2" />')
        ->assertSeeInOrder(['Step 2 of 3: Details', 'aria-current="step"'], escape: false);
});

it('explains when no times are published instead of inventing slots', function () {
    $this->blade('<x-ui.slot-picker name="time" legend="Time" state="unconfigured" />')
        ->assertSee('Times are not published yet')
        ->assertDontSee('type="radio"', escape: false);
});

it('falls back to "on request" for an unknown availability state', function () {
    $this->blade('<x-ui.availability status="something-else" />')->assertSee('Availability on request');
});

it('labels a missing photo as a placeholder instead of showing an image', function () {
    $this->blade('<x-ui.photo />')
        ->assertSee('Photo placeholder: Photography to be supplied by SmartGro')
        ->assertDontSee('<img', escape: false);
});

it('renders a disabled button that cannot be activated', function () {
    $this->blade('<x-ui.button href="/book" disabled>Book a tour</x-ui.button>')
        ->assertSee('disabled', escape: false)
        ->assertDontSee('href="/book"', escape: false);
});

it('marks a loading button as busy and disabled', function () {
    $this->blade('<x-ui.button loading>Sending</x-ui.button>')
        ->assertSee('aria-busy="true"', escape: false)
        ->assertSee('disabled', escape: false);
});

it('links a field error to its control for screen readers', function () {
    $this->blade(<<<'BLADE'
        <x-form.field label="Work email" for="email" error="Enter a valid email address.">
            <x-form.input id="email" invalid described-by="email-error" />
        </x-form.field>
        BLADE)
        ->assertSee('aria-invalid="true"', escape: false)
        ->assertSee('aria-describedby="email-error"', escape: false)
        ->assertSee('id="email-error"', escape: false);
});
