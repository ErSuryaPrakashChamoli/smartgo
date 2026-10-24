<?php

it('escapes property details in the property card', function () {
    $this->blade('<x-card.property :name="$name" area="Sector 2" city="Noida" />', [
        'name' => "O'Reilly <script>alert('xss')</script>",
    ])
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee("<script>alert('xss')</script>", escape: false);
});

it('shows price and availability on request when a property has none approved', function () {
    $this->blade('<x-card.property name="A-23" area="Sector 3" city="Noida" />')
        ->assertSee('Price on request')
        ->assertSee('Availability on request');
});
