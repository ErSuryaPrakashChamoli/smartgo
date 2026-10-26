<?php

namespace App\Services\Content;

use Illuminate\Support\Str;

/**
 * Turns a page payload into view data. The public page and the staff preview both use it, so a
 * preview shows a version exactly as it would be published (BR-CMS-4).
 */
class PageRenderer
{
    public function __construct(private BlockLibrary $blockLibrary) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: string, blocks: list<array{component: string, type: string, data: array<string, mixed>}>, startsWithIntroduction: bool, metaTitle: string|null, metaDescription: string|null, indexable: bool}
     */
    public function present(array $payload): array
    {
        $blocks = $this->blockLibrary->present(is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : []);
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];

        return [
            'title' => (string) ($payload['title'] ?? ''),
            'blocks' => $blocks,
            'startsWithIntroduction' => ($blocks[0]['type'] ?? null) === 'hero',
            'metaTitle' => filled($seo['title'] ?? null) ? (string) $seo['title'] : null,
            'metaDescription' => filled($seo['description'] ?? null) ? (string) $seo['description'] : $this->defaultDescription($blocks),
            'indexable' => (bool) ($seo['indexable'] ?? true),
        ];
    }

    /**
     * Longest automatic description, ellipsis included (the limit editors have for their own).
     */
    public const int DESCRIPTION_LIMIT = 160;

    /**
     * When no description is written, the introduction's text, or else the first text section, cut on a
     * word boundary so that the result, ellipsis included, never exceeds DESCRIPTION_LIMIT (FR-SEO-1).
     *
     * @param  list<array{component: string, type: string, data: array<string, mixed>}>  $blocks
     */
    private function defaultDescription(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            $text = match ($block['type']) {
                'hero' => $block['data']['intro'] ?? null,
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
