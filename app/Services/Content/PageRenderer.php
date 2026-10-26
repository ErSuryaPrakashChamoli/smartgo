<?php

namespace App\Services\Content;

/**
 * Turns a page payload into view data. The public page and the staff preview both use it, so a
 * preview shows a version exactly as it would be published (BR-CMS-4).
 */
class PageRenderer
{
    public function __construct(private BlockLibrary $blockLibrary) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: string, blocks: list<array{component: string, type: string, data: array<string, mixed>}>, startsWithIntroduction: bool}
     */
    public function present(array $payload): array
    {
        $blocks = $this->blockLibrary->present(is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : []);

        return [
            'title' => (string) ($payload['title'] ?? ''),
            'blocks' => $blocks,
            'startsWithIntroduction' => ($blocks[0]['type'] ?? null) === 'hero',
        ];
    }
}
