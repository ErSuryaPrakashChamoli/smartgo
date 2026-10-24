<?php

use App\Services\Design\DesignTokens;

dataset('pairings in use', fn (): array => collect(DesignTokens::PAIRINGS)
    ->mapWithKeys(fn (array $pairing): array => ["{$pairing['foreground']} on {$pairing['background']} ({$pairing['usage']})" => [$pairing]])
    ->all());

test('every colour pairing in use meets its WCAG 2.2 AA minimum', function (array $pairing) {
    $colors = app(DesignTokens::class)->colors();

    $ratio = app(DesignTokens::class)->contrastRatio($colors[$pairing['foreground']], $colors[$pairing['background']]);

    expect($ratio)->toBeGreaterThanOrEqual($pairing['minimum']);
})->with('pairings in use');

test('every pairing refers to a colour defined in the stylesheet', function () {
    $defined = array_keys(app(DesignTokens::class)->colors());

    $referenced = collect(DesignTokens::PAIRINGS)->flatMap(fn (array $pairing): array => [$pairing['foreground'], $pairing['background']])->unique()->all();

    expect(array_diff($referenced, $defined))->toBeEmpty();
});

test('contrast ratios follow the WCAG formula', function (string $foreground, string $background, float $expected) {
    expect(app(DesignTokens::class)->contrastRatio($foreground, $background))->toBe($expected);
})->with([
    'black on white' => ['#000000', '#ffffff', 21.0],
    'identical colours' => ['#2e8b18', '#2e8b18', 1.0],
    'grey just above the AA threshold, rounded down' => ['#767676', '#ffffff', 4.54],
]);
