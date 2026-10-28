<?php

namespace App\Services\Content;

use App\Models\NavigationMenu;
use App\Models\Property;
use App\Models\SiteSetting;
use App\Services\Website\SiteSections;

/**
 * The website's published menus, contact details and social links, ready for the header and footer.
 * A link to a page appears only while that page is published, a link to a property only while it is
 * listed, and a link to a part of the website only while it has something to show, always at the
 * current address; groups without a visible link, and menus and settings that were never published,
 * are left out, so nothing unapproved or placeholder is shown (BR-CMS-9).
 */
class SiteNavigation
{
    public function __construct(private ContentLink $contentLink, private SiteSections $siteSections) {}

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

        $paths = [
            ContentLink::TO_PAGE => $this->contentLink->publishedPagePaths($this->linkIds($payloads, 'page_id')),
            NavigationPayload::SECTION => $this->siteSections->availablePaths(array_values(array_filter($this->linkTargets($payloads, 'section'), is_string(...)))),
            NavigationPayload::PROPERTY => $this->listedPropertyPaths($this->linkIds($payloads, 'property_id')),
        ];

        $settings = SiteSetting::query()->whereIn('handle', ['contact', 'social'])->get()->keyBy('handle');

        return [
            ...array_map(fn (array $items): array => $this->resolve($items, $paths), $payloads),
            'contact' => $this->contact($settings->get('contact')),
            'social' => $this->social($settings->get('social')),
        ];
    }

    /**
     * The same structure without any menu, contact detail or social link, for pages shown when the
     * published menus cannot be read, such as the server error page.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function withoutMenus(): array
    {
        return [...array_fill_keys(array_keys(NavigationMenu::STRUCTURES), []), 'contact' => [], 'social' => []];
    }

    /**
     * SmartGro's main published contact details on their own, for a page section that shows them.
     *
     * @return list<array{label: string, text: string, href: string|null}>
     */
    public function mainContact(): array
    {
        return $this->contact(SiteSetting::query()->where('handle', 'contact')->first());
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

        return $this->contactDetails(collect(['phone', 'whatsapp', 'email', 'address', 'opening_hours'])->mapWithKeys(fn (string $key): array => [$key => $setting->value($key)])->all());
    }

    /**
     * Contact details ready to show, with call, WhatsApp and email links, in a fixed order; empty
     * values are left out. Used for SmartGro's main details and a location's own.
     *
     * @param  array<string, mixed>  $values
     * @return list<array{label: string, text: string, href: string|null}>
     */
    public function contactDetails(array $values): array
    {
        $value = fn (string $key): string => trim((string) ($values[$key] ?? ''));

        return array_values(array_filter([
            $value('phone') !== '' ? ['label' => 'Phone', 'text' => $value('phone'), 'href' => 'tel:'.preg_replace('/[^0-9+]/', '', $value('phone'))] : null,
            $value('whatsapp') !== '' ? ['label' => 'WhatsApp', 'text' => $value('whatsapp'), 'href' => 'https://wa.me/'.ltrim($value('whatsapp'), '+')] : null,
            $value('email') !== '' ? ['label' => 'Email', 'text' => $value('email'), 'href' => 'mailto:'.$value('email')] : null,
            $value('address') !== '' ? ['label' => 'Address', 'text' => $value('address'), 'href' => null] : null,
            $value('opening_hours') !== '' ? ['label' => 'Opening hours', 'text' => $value('opening_hours'), 'href' => null] : null,
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
     * @param  array<string, array<int|string, string>>  $paths  link type => target => address, for the targets that may be shown
     * @return list<array{label: string, href: string|null, external: bool, children: list<array<string, mixed>>}>
     */
    private function resolve(array $items, array $paths): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === NavigationPayload::GROUP) {
                $children = $this->resolve($item['children'] ?? [], $paths);

                if ($children !== [] && filled($item['label'] ?? null)) {
                    $resolved[] = ['label' => (string) $item['label'], 'href' => null, 'external' => false, 'children' => $children];
                }

                continue;
            }

            $href = match ($item['type'] ?? null) {
                ContentLink::TO_PAGE => $paths[ContentLink::TO_PAGE][(int) ($item['page_id'] ?? 0)] ?? null,
                NavigationPayload::SECTION => $paths[NavigationPayload::SECTION][(string) ($item['section'] ?? '')] ?? null,
                NavigationPayload::PROPERTY => $paths[NavigationPayload::PROPERTY][(int) ($item['property_id'] ?? 0)] ?? null,
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
     * Addresses of the given properties that are listed on the website, keyed by property ID.
     *
     * @param  list<int>  $propertyIds
     * @return array<int, string>
     */
    private function listedPropertyPaths(array $propertyIds): array
    {
        return $propertyIds === [] ? [] : Property::query()->whereKey(array_unique($propertyIds))->publiclyListed()->with('city')->get()
            ->mapWithKeys(fn (Property $property): array => [$property->getKey() => (string) $property->path()])
            ->all();
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $payloads
     * @return list<int>
     */
    private function linkIds(array $payloads, string $field): array
    {
        return array_map(intval(...), array_values(array_filter($this->linkTargets($payloads, $field), is_numeric(...))));
    }

    /**
     * Every value the menus hold under the given key, such as each page ID.
     *
     * @param  array<string, list<array<string, mixed>>>  $payloads
     * @return list<mixed>
     */
    private function linkTargets(array $payloads, string $field): array
    {
        $targets = [];

        array_walk_recursive($payloads, function (mixed $value, string|int $key) use (&$targets, $field): void {
            if ($key === $field) {
                $targets[] = $value;
            }
        });

        return $targets;
    }
}
