<?php

it('frames no Google map until the visitor asks for one, and links to the address in Google Maps', function () {
    $this->blade('<x-site.google-map address="Tower B, Sector 62
Noida" />')
        ->assertDontSee('<iframe', false)
        ->assertSee('data-map-src="https://www.google.com/maps?q=Tower%20B%2C%20Sector%2062%20Noida&amp;output=embed"', false)
        ->assertSee('data-map-title="Map of Tower B, Sector 62 Noida"', false)
        ->assertSee('data-map-load hidden>Show map</button>', false)
        ->assertSee('data-map-note hidden>Loads Google Maps, which may set cookies. This device will remember your choice.</p>', false)
        ->assertSee('href="https://www.google.com/maps/search/?api=1&amp;query=Tower%20B%2C%20Sector%2062%20Noida"', false)
        ->assertSee('Open in Google Maps');
});

it('keeps an address with quotes and ampersands inside the map addresses', function () {
    $this->blade('<x-site.google-map :address="$address" />', ['address' => 'A & B "Plaza" #2'])
        ->assertSee('q=A%20%26%20B%20%22Plaza%22%20%232&amp;output=embed', false)
        ->assertSee('data-map-title="Map of A &amp; B &quot;Plaza&quot; #2"', false);
});
