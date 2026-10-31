<?php

namespace App\Services\Content;

use App\Models\MediaAsset;
use Illuminate\Support\Str;

/**
 * Turns a page payload into view data. The public page and the staff preview both use it, so a
 * preview shows a version exactly as it would be published (BR-CMS-4).
 */
class PageRenderer
{
    public function __construct(private BlockLibrary $blockLibrary, private RichText $richText) {}

    /**
     * Only what a page tells search engines: its title, search title, search description and whether it
     * may be indexed, worked out exactly as present() does but without presenting the sections, which
     * may load contact details, locations, media or links. The default description reads only hero and
     * contact introductions and text bodies (defaultDescription), so only those are prepared here. Used
     * by the search titles report (FR-SEO-1), so its cost does not grow with what sections show.
     *
     * @param  array<string, mixed>  $payload
     * @return array{title: string, metaTitle: string|null, metaDescription: string|null, indexable: bool}
     */
    public function searchFields(array $payload): array
    {
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];
        $sections = collect(is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [])
            ->filter(fn (mixed $block): bool => is_array($block) && in_array($block['type'] ?? null, ['hero', 'contact', 'text'], true) && is_array($block['data'] ?? null))
            ->map(fn (array $block): array => ['type' => $block['type'], 'data' => match ($block['type']) {
                'text' => ['body' => $this->richText->sanitize($block['data']['body'] ?? null)],
                'contact' => ['intro' => filled($block['data']['intro'] ?? null) ? (string) $block['data']['intro'] : null],
                default => ['intro' => $block['data']['intro'] ?? null],
            }])
            ->values()->all();

        return [
            'title' => (string) ($payload['title'] ?? ''),
            'metaTitle' => filled($seo['title'] ?? null) ? (string) $seo['title'] : null,
            'metaDescription' => filled($seo['description'] ?? null) ? (string) $seo['description'] : $this->defaultDescription($sections),
            'indexable' => (bool) ($seo['indexable'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: string, blocks: list<array{component: string, type: string, data: array<string, mixed>}>, startsWithIntroduction: bool, metaTitle: string|null, metaDescription: string|null, indexable: bool, shareImagePath: string|null}
     */
    public function present(array $payload): array
    {
        $blocks = $this->blockLibrary->present(is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : []);
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];

        return [
            'title' => (string) ($payload['title'] ?? ''),
            'blocks' => $blocks,
            'startsWithIntroduction' => ($blocks[0]['type'] ?? null) === 'hero',
            'template' => (string) ($payload['template'] ?? 'standard'),
            'metaTitle' => filled($seo['title'] ?? null) ? (string) $seo['title'] : null,
            'metaDescription' => filled($seo['description'] ?? null) ? (string) $seo['description'] : $this->defaultDescription($blocks),
            'indexable' => (bool) ($seo['indexable'] ?? true),
            'shareImagePath' => $this->shareImagePath($seo['image_asset_id'] ?? null),
        ];
    }

    /**
     * The share image's rendition, or null when there is none or its licence has expired (BR-MED-3).
     */
    private function shareImagePath(mixed $assetId): ?string
    {
        $asset = is_numeric($assetId) ? MediaAsset::query()->with('media')->find((int) $assetId) : null;

        return $asset === null || $asset->isLicenceExpired() || $asset->file() === null ? null : $asset->renditionPath('w1600');
    }

    /**
     * Longest automatic description, ellipsis included (the limit editors have for their own).
     */
    public const int DESCRIPTION_LIMIT = 160;

    /**
     * When no description is written, the introduction's text, or else the first text section or contact
     * details section's introduction, cut on a word boundary so that the result, ellipsis included, never
     * exceeds DESCRIPTION_LIMIT (FR-SEO-1).
     *
     * @param  list<array{component: string, type: string, data: array<string, mixed>}>  $blocks
     */
    private function defaultDescription(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            $text = match ($block['type']) {
                'hero', 'contact' => $block['data']['intro'] ?? null,
                'text' => html_entity_decode(strip_tags(str_replace('</p>', '</p> ', (string) ($block['data']['body'] ?? ''))), ENT_QUOTES | ENT_HTML5),
                default => null,
            };
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

            if ($text !== '') {
                return Str::limit($text, self::DESCRIPTION_LIMIT - 1, '…', preserveWords: true);
            }
        }

        return null;
    }
}
