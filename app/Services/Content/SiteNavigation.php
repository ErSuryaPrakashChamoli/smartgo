<?php

namespace App\Services\Content;

use App\Models\NavigationMenu;

/**
 * The website's published menus, ready for the header and footer. A link to a page appears only while
 * that page is published, and always at its current address; groups without a visible link and menus
 * that were never published are left out, so nothing unapproved or placeholder is shown.
 */
class SiteNavigation
{
    public function __construct(private ContentLink $contentLink) {}

    /**
     * @param  array<string, array<string, mixed>>  $draftPayloads  menu handle => payload shown instead of the live menu (staff preview only)
     * @return array<string, list<array{label: string, href: string|null, external: bool, children: list<array{label: string, href: string, external: bool}>}>>
     */
    public function present(array $draftPayloads = []): array
    {
        $menus = NavigationMenu::query()->get()->keyBy('handle');
        $payloads = [];

        foreach (array_keys(NavigationMenu::STRUCTURES) as $handle) {
            $payloads[$handle] = $draftPayloads[$handle]['items'] ?? $menus->get($handle)?->toRevisionPayload()['items'] ?? [];
        }

        $pagePaths = $this->contentLink->publishedPagePaths($this->pageIds($payloads));

        return array_map(fn (array $items): array => $this->resolve($items, $pagePaths), $payloads);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, string>  $pagePaths
     * @return list<array{label: string, href: string|null, external: bool, children: list<array<string, mixed>>}>
     */
    private function resolve(array $items, array $pagePaths): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === NavigationPayload::GROUP) {
                $children = $this->resolve($item['children'] ?? [], $pagePaths);

                if ($children !== [] && filled($item['label'] ?? null)) {
                    $resolved[] = ['label' => (string) $item['label'], 'href' => null, 'external' => false, 'children' => $children];
                }

                continue;
            }

            $href = match ($item['type'] ?? null) {
                ContentLink::TO_PAGE => $pagePaths[(int) ($item['page_id'] ?? 0)] ?? null,
                ContentLink::TO_URL => is_string($item['url'] ?? null) && $this->contentLink->isSafeExternalUrl($item['url']) ? $item['url'] : null,
                default => null,
            };

            if ($href !== null && filled($item['label'] ?? null)) {
                $resolved[] = ['label' => (string) $item['label'], 'href' => $href, 'external' => $item['type'] === ContentLink::TO_URL, 'children' => []];
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $payloads
     * @return list<int>
     */
    private function pageIds(array $payloads): array
    {
        $ids = [];

        array_walk_recursive($payloads, function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'page_id' && is_numeric($value)) {
                $ids[] = (int) $value;
            }
        });

        return $ids;
    }
}
