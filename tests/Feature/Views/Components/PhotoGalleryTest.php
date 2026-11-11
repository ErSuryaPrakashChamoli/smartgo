<?php

/**
 * A photo as WorkspaceCatalogue and SpaceListingPages present it.
 *
 * @return array{src: string, srcset: string, alt: string, credit: string|null, dimensions: array{width: int, height: int}, label: string|null}
 */
function galleryPhotoForTest(int $id, ?string $label = null, ?string $credit = null): array
{
    return [
        'src' => "/media/{$id}/w960",
        'srcset' => "/media/{$id}/w480 480w, /media/{$id}/w960 960w, /media/{$id}/w1600 1600w",
        'alt' => "Photo number {$id}",
        'credit' => $credit,
        'dimensions' => ['width' => 2400, 'height' => 1600],
        'label' => $label,
    ];
}

it('shows the first photo in the main frame and every photo as a small button that shows it there instead, never full screen (OD-183)', function () {
    $view = $this->blade('<x-site.photo-gallery :photos="$photos" />', ['photos' => [
        galleryPhotoForTest(1, 'Stock photo, not a SmartGro location', 'Photo: Example on StockSnap (CC0)'),
        galleryPhotoForTest(2),
        galleryPhotoForTest(3, 'Stock photo, not a SmartGro location'),
    ]]);

    $view->assertSee('data-photo-gallery', false)
        ->assertSeeInOrder(['src="/media/1/w960"', 'alt="Photo number 1"', 'data-photo-main'], false)
        ->assertSeeInOrder(['data-photo-label', 'Stock photo, not a SmartGro location'], false)
        ->assertSee('Photo: Example on StockSnap (CC0)')
        ->assertSeeInOrder([
            'aria-label="All 3 photos: choose one to show it above"',
            '<button type="button" aria-pressed="true"', 'data-src="/media/1/w960"', 'Show photo 1 of 3: Photo number 1',
            '<button type="button" aria-pressed="false"', 'data-src="/media/2/w960"', 'data-label=""', 'Show photo 2 of 3: Photo number 2',
            '<button type="button" aria-pressed="false"', 'data-label="Stock photo, not a SmartGro location"', 'Show photo 3 of 3: Photo number 3',
        ], false)
        ->assertDontSee('w1600"', false)
        ->assertDontSee('Enlarge')
        ->assertDontSee('<a ', false);

    expect(substr_count((string) $view, 'data-photo-thumb'))->toBe(3);
});

it('keeps the main photo\'s label in place but hidden when the first photo has none, so a stock photo chosen later can show it', function () {
    $view = $this->blade('<x-site.photo-gallery :photos="$photos" />', ['photos' => [galleryPhotoForTest(1), galleryPhotoForTest(2, 'Stock photo, not a SmartGro location')]]);

    expect(preg_match('/<span\b[^>]*data-photo-label[^>]*>/', (string) $view, $label))->toBe(1)
        ->and($label[0])->toContain('hidden');
});

it('shows a single photo large with no small photos', function () {
    $this->blade('<x-site.photo-gallery :photos="$photos" />', ['photos' => [galleryPhotoForTest(7)]])
        ->assertSee('data-photo-main', false)
        ->assertSee('src="/media/7/w960"', false)
        ->assertDontSee('data-photo-thumb', false)
        ->assertDontSee('All 1 photos');
});
