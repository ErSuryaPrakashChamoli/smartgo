<?php

it('formats an approved price in rupees with Indian digit grouping', function () {
    $this->blade('<x-ui.price :amount="125000" unit="month" />')
        ->assertSeeInOrder(['from', '₹1,25,000', '/ month', '+ GST']);
});

it('shows "Price on request" when no price is approved', function () {
    $this->blade('<x-ui.price />')
        ->assertSee('Price on request')
        ->assertDontSee('₹');
});

it('gives every availability state a text label, not colour alone', function (string $status, string $label) {
    $this->blade('<x-ui.availability :status="$status" />', ['status' => $status])->assertSee($label);
})->with([
    'available' => ['available', 'Available'],
    'limited' => ['limited', 'Limited availability'],
    'unavailable' => ['unavailable', 'Fully occupied'],
    'on request' => ['on-request', 'Availability on request'],
]);

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
