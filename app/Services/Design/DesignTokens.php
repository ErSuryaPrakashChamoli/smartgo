<?php

namespace App\Services\Design;

use RuntimeException;

/**
 * Reads the colour tokens from resources/css/app.css (the single source of truth) and lists the
 * foreground/background pairings the design system uses, so their WCAG contrast can be shown
 * in the design preview and enforced by tests (Phase 2, AC-2.1).
 */
class DesignTokens
{
    /**
     * Normal text needs 4.5:1; large text (≥ 24 px, or ≥ 18.66 px bold), icons and control
     * boundaries need 3:1 (WCAG 2.2 SC 1.4.3 and 1.4.11).
     *
     * @var list<array{foreground: string, background: string, minimum: float, usage: string}>
     */
    public const PAIRINGS = [
        ['foreground' => 'ink', 'background' => 'paper', 'minimum' => 4.5, 'usage' => 'Body text on the page'],
        ['foreground' => 'ink', 'background' => 'surface', 'minimum' => 4.5, 'usage' => 'Body text on cards'],
        ['foreground' => 'muted', 'background' => 'paper', 'minimum' => 4.5, 'usage' => 'Secondary text on the page'],
        ['foreground' => 'muted', 'background' => 'surface', 'minimum' => 4.5, 'usage' => 'Secondary text on cards'],
        ['foreground' => 'muted', 'background' => 'mist', 'minimum' => 4.5, 'usage' => 'Secondary text on tinted panels'],
        ['foreground' => 'forest', 'background' => 'paper', 'minimum' => 4.5, 'usage' => 'Headings'],
        ['foreground' => 'forest', 'background' => 'mist', 'minimum' => 4.5, 'usage' => 'Headings and badges on tinted panels'],
        ['foreground' => 'leaf-700', 'background' => 'paper', 'minimum' => 4.5, 'usage' => 'Links and eyebrows on the page'],
        ['foreground' => 'leaf-700', 'background' => 'surface', 'minimum' => 4.5, 'usage' => 'Links on cards'],
        ['foreground' => 'leaf-700', 'background' => 'mist', 'minimum' => 4.5, 'usage' => 'Links and icons on tinted panels'],
        ['foreground' => 'white', 'background' => 'leaf-700', 'minimum' => 4.5, 'usage' => 'Primary button'],
        ['foreground' => 'white', 'background' => 'leaf-800', 'minimum' => 4.5, 'usage' => 'Primary button, hover'],
        ['foreground' => 'paper', 'background' => 'forest', 'minimum' => 4.5, 'usage' => 'Text on dark sections'],
        ['foreground' => 'leaf-300', 'background' => 'forest', 'minimum' => 4.5, 'usage' => 'Accent text on dark sections'],
        ['foreground' => 'paper', 'background' => 'forest-soft', 'minimum' => 4.5, 'usage' => 'Text on dark panels'],
        ['foreground' => 'leaf-600', 'background' => 'paper', 'minimum' => 3.0, 'usage' => 'Large display text accent'],
        ['foreground' => 'control', 'background' => 'surface', 'minimum' => 3.0, 'usage' => 'Form control borders'],
        ['foreground' => 'control', 'background' => 'paper', 'minimum' => 3.0, 'usage' => 'Form control borders on the page'],
        ['foreground' => 'available', 'background' => 'available-soft', 'minimum' => 4.5, 'usage' => 'Availability: available'],
        ['foreground' => 'limited', 'background' => 'limited-soft', 'minimum' => 4.5, 'usage' => 'Availability: limited; warnings'],
        ['foreground' => 'unavailable', 'background' => 'unavailable-soft', 'minimum' => 4.5, 'usage' => 'Availability: fully occupied; errors'],
        ['foreground' => 'info', 'background' => 'info-soft', 'minimum' => 4.5, 'usage' => 'Information messages'],
        ['foreground' => 'danger', 'background' => 'surface', 'minimum' => 4.5, 'usage' => 'Field error messages'],
        ['foreground' => 'danger', 'background' => 'paper', 'minimum' => 4.5, 'usage' => 'Field error messages on the page'],
    ];

    /**
     * @return array<string, string> token name => lowercase hex value
     */
    public function colors(): array
    {
        $css = file_get_contents(resource_path('css/app.css'));

        if ($css === false) {
            throw new RuntimeException('resources/css/app.css could not be read.');
        }

        preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $css, $matches, PREG_SET_ORDER);

        $colors = [];

        foreach ($matches as [, $name, $hex]) {
            $colors[$name] = strtolower($hex);
        }

        return $colors;
    }

    /**
     * @return list<array{foreground: string, background: string, minimum: float, usage: string, ratio: float, passes: bool}>
     */
    public function pairings(): array
    {
        $colors = $this->colors();

        return array_map(function (array $pairing) use ($colors): array {
            $ratio = $this->contrastRatio($colors[$pairing['foreground']], $colors[$pairing['background']]);

            return [...$pairing, 'ratio' => $ratio, 'passes' => $ratio >= $pairing['minimum']];
        }, self::PAIRINGS);
    }

    /**
     * WCAG 2.x contrast ratio between two hex colours, rounded down to two decimals so a
     * borderline pair is never reported as passing.
     */
    public function contrastRatio(string $foreground, string $background): float
    {
        $lighter = max($this->relativeLuminance($foreground), $this->relativeLuminance($background));
        $darker = min($this->relativeLuminance($foreground), $this->relativeLuminance($background));

        return floor(($lighter + 0.05) / ($darker + 0.05) * 100) / 100;
    }

    private function relativeLuminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
