<?php

namespace App\Services\Content;

use App\Models\NavigationMenu;
use App\Models\SiteSetting;

/**
 * The website's published menus, contact details and social links, ready for the header and footer.
 * A link to a page appears only while that page is published, and always at its current address;
 * groups without a visible link, and menus and settings that were never published, are left out, so
 * nothing unapproved or placeholder is shown (BR-CMS-9).
 */
class SiteNavigation
{
    public function __construct(private ContentLink $contentLink) {}

    /**
     * @param  array<string, array<string, mixed>>  $draftPayloads  menu handle => payload shown instead of the live menu (staff preview only)
     * @return array<string, mixed> each menu handle => list<array{label: string, href: string|null, external: bool, children: list<array{label: string, href: string, external: bool}>}>, plus `contact` and `social`
     */
    public function present(array $draftPayloads = []): array
    {
        $menus = NavigationMenu::query()->get()->keyBy('handle');
        $payloads = [];

        foreach (array_keys(NavigationMenu::STRUCTURES) as $handle) {
            $payloads[$handle] = $draftPayloads[$handle]['items'] ?? $menus->get($handle)?->toRevisionPayload()['items'] ?? [];
        }

        $pagePaths = $this->contentLink->publishedPagePaths($this->pageIds($payloads));

        $settings = SiteSetting::query()->whereIn('handle', ['contact', 'social'])->get()->keyBy('handle');

        return [
            ...array_map(fn (array $items): array => $this->resolve($items, $pagePaths), $payloads),
            'contact' => $this->contact($settings->get('contact')),
            'social' => $this->social($settings->get('social')),
        ];
    }

    /**
     * The published contact details; empty when none are published.
     *
     * @return list<array{label: string, text: string, href: string|null}>
     */
    private function contact(?SiteSetting $setting): array
    {
        if ($setting === null || ! $setting->isPublished()) {
            return [];
        }

        return array_values(array_filter([
            filled($setting->value('phone')) ? ['label' => 'Phone', 'text' => (string) $setting->value('phone'), 'href' => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $setting->value('phone'))] : null,
            filled($setting->value('whatsapp')) ? ['label' => 'WhatsApp', 'text' => (string) $setting->value('whatsapp'), 'href' => 'https://wa.me/'.ltrim((string) $setting->value('whatsapp'), '+')] : null,
            filled($setting->value('email')) ? ['label' => 'Email', 'text' => (string) $setting->value('email'), 'href' => 'mailto:'.$setting->value('email')] : null,
            filled($setting->value('address')) ? ['label' => 'Address', 'text' => (string) $setting->value('address'), 'href' => null] : null,
            filled($setting->value('opening_hours')) ? ['label' => 'Opening hours', 'text' => (string) $setting->value('opening_hours'), 'href' => null] : null,
        ]));
    }

    /**
     * The published social links, re-checked against their network, in a fixed order.
     *
     * @return list<array{label: string, href: string}>
     */
    private function social(?SiteSetting $setting): array
    {
        if ($setting === null || ! $setting->isPublished()) {
            return [];
        }

        $labels = ['linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'youtube' => 'YouTube'];
        $links = [];

        foreach ($labels as $field => $label) {
            $url = $setting->value($field);

            if (is_string($url) && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), SiteSettingPayload::SOCIAL_HOSTS[$field], true) && str_starts_with($url, 'https://')) {
                $links[] = ['label' => $label, 'href' => $url];
            }
        }

        return $links;
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
