<?php

namespace App\Services\Content;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Models\SiteSetting;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Services\Website\EnquiryForm;
use App\Services\Website\SiteSections;
use App\Services\Website\SpaceListingPages;
use App\Services\Website\WorkspaceCatalogue;

/**
 * The website's published menus, contact details and social links, ready for the header and footer.
 * A link to a page appears only while that page is published, a link to a property only while it is
 * listed, a link to a workspace type while it is shown (to its page, to the enquiry form while it has
 * none, or to its card on the workspaces page while the form is closed), and a link to a part of the
 * website only while it has something to show, always at the
 * current address; groups without a visible link, and menus and settings that were never published,
 * are left out, so nothing unapproved or placeholder is shown (BR-CMS-9). Drop-down links and categories
 * carry their description and icon (OD-149), and a link to a sample page says so, for the "example pages" note.
 */
class SiteNavigation
{
    public function __construct(private ContentLink $contentLink, private SiteSections $siteSections) {}

    /**
     * @param  array<string, array<string, mixed>>  $draftPayloads  menu handle => payload shown instead of the live menu (staff preview only)
     * @return array<string, mixed> each menu handle => list<array{label: string, href: string|null, external: bool, description: string|null, icon: string|null, sample: bool, children: list<array<string, mixed>>}>, plus `contact` and `social`
     */
    public function present(array $draftPayloads = []): array
    {
        $menus = NavigationMenu::query()->get()->keyBy('handle');
        $payloads = [];

        foreach (array_keys(NavigationMenu::STRUCTURES) as $handle) {
            $payloads[$handle] = $draftPayloads[$handle]['items'] ?? $menus->get($handle)?->toRevisionPayload()['items'] ?? [];
        }

        $pageIds = $this->linkIds($payloads, 'page_id');
        $pages = $pageIds === [] ? collect() : Page::query()->whereKey(array_unique($pageIds))->published()->get(['id', 'slug', 'template']);
        $paths = [
            ContentLink::TO_PAGE => $pages->mapWithKeys(fn (Page $page): array => [$page->getKey() => '/'.$page->slug])->all(),
            'samples' => $pages->where('template', PagePayload::SAMPLE_TEMPLATE)->pluck('id')->all(),
            NavigationPayload::SECTION => $this->siteSections->availablePaths(array_values(array_filter($this->linkTargets($payloads, 'section'), is_string(...)))),
            NavigationPayload::PROPERTY => $this->listedPropertyPaths($this->linkIds($payloads, 'property_id')),
            NavigationPayload::CATEGORY => $this->categoryPaths($this->linkIds($payloads, 'product_id')),
        ];
        $paths['icons'] = $paths[NavigationPayload::CATEGORY] === [] ? [] : Product::query()->whereKey(array_keys($paths[NavigationPayload::CATEGORY]))->get(['id', 'key'])
            ->mapWithKeys(fn (Product $product): array => [$product->getKey() => app(WorkspaceCatalogue::class)->categoryCopy($product)['icon']])->all();
        $spaceTypeIds = $this->linkIds($payloads, 'space_type_id');
        $paths['spaces'] = app(SpaceListingPages::class)->menu($spaceTypeIds);
        $paths['spaceTypeIcons'] = $paths['spaces'] === [] ? [] : SpaceType::query()->whereKey(array_keys($paths['spaces']))->pluck('key', 'id')
            ->map(fn (string $key): ?string => SpaceListing::TYPE_ICONS[$key] ?? null)->all();

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
     * @return list<array{network: string, label: string, href: string}>
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
                $links[] = ['network' => $field, 'label' => $label, 'href' => $url];
            }
        }

        return $links;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, array<int|string, string>>  $paths  link type => target => address, for the targets that may be shown
     * @return list<array{label: string, href: string|null, external: bool, description: string|null, icon: string|null, sample: bool, children: list<array<string, mixed>>}>
     */
    private function resolve(array $items, array $paths): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === NavigationPayload::GROUP) {
                $children = $this->resolve($item['children'] ?? [], $paths);

                if ($children !== [] && filled($item['label'] ?? null)) {
                    $resolved[] = [
                        'label' => (string) $item['label'],
                        'href' => null,
                        'external' => false,
                        'description' => $this->description($item),
                        'icon' => $this->chosenIcon($item) ?? collect($children)->pluck('icon')->filter()->first(),
                        'sample' => collect($children)->contains('sample', true),
                        'children' => $children,
                    ];
                }

                continue;
            }

            // A space type opens as a tab of its shown spaces, each with its location (OD-177); none shown, no tab.
            if (($item['type'] ?? null) === NavigationPayload::SPACE_TYPE) {
                $spaces = $paths['spaces'][(int) ($item['space_type_id'] ?? 0)] ?? [];

                if ($spaces !== [] && filled($item['label'] ?? null)) {
                    $resolved[] = [
                        'label' => (string) $item['label'],
                        'href' => null,
                        'external' => false,
                        'description' => $this->description($item),
                        'icon' => $this->chosenIcon($item) ?? $paths['spaceTypeIcons'][(int) $item['space_type_id']] ?? null,
                        'sample' => collect($spaces)->contains('sample', true),
                        'kind' => NavigationPayload::SPACE_TYPE,
                        'children' => array_map(fn (array $space): array => [
                            'label' => $space['label'],
                            'href' => $space['href'],
                            'external' => false,
                            'description' => collect([$space['location'], $space['area']])->filter()->join(' · '),
                            'icon' => null,
                            'sample' => $space['sample'],
                            'location' => $space['location'],
                            'area' => $space['area'],
                            'children' => [],
                        ], $spaces),
                    ];
                }

                continue;
            }

            $href = match ($item['type'] ?? null) {
                ContentLink::TO_PAGE => $paths[ContentLink::TO_PAGE][(int) ($item['page_id'] ?? 0)] ?? null,
                NavigationPayload::SECTION => $paths[NavigationPayload::SECTION][(string) ($item['section'] ?? '')] ?? null,
                NavigationPayload::PROPERTY => $paths[NavigationPayload::PROPERTY][(int) ($item['property_id'] ?? 0)] ?? null,
                NavigationPayload::CATEGORY => $paths[NavigationPayload::CATEGORY][(int) ($item['product_id'] ?? 0)] ?? null,
                ContentLink::TO_URL => is_string($item['url'] ?? null) && $this->contentLink->isSafeExternalUrl($item['url']) ? $item['url'] : null,
                default => null,
            };

            if ($href !== null && filled($item['label'] ?? null)) {
                $resolved[] = [
                    'label' => (string) $item['label'],
                    'href' => $href,
                    'external' => $item['type'] === ContentLink::TO_URL,
                    'description' => $this->description($item),
                    'icon' => $this->chosenIcon($item) ?? $this->icon($item, $paths['icons']),
                    'sample' => $item['type'] === ContentLink::TO_PAGE && in_array((int) ($item['page_id'] ?? 0), $paths['samples'], true),
                    'children' => [],
                ];
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
     * The icon an editor chose for the item (OD-149), if it is still one the website offers.
     *
     * @param  array<string, mixed>  $item
     */
    private function chosenIcon(array $item): ?string
    {
        $icon = $item['icon'] ?? null;

        return is_string($icon) && array_key_exists($icon, NavigationItem::ICONS) ? $icon : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function description(array $item): ?string
    {
        return is_string($item['description'] ?? null) && filled($item['description']) ? $item['description'] : null;
    }

    /**
     * A small picture for a link where its destination has one (OD-145): a workspace type's own icon, a pin
     * for a location, a speech bubble for the enquiry form.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string|null>  $categoryIcons
     */
    private function icon(array $item, array $categoryIcons): ?string
    {
        return match ($item['type'] ?? null) {
            NavigationPayload::CATEGORY => $categoryIcons[(int) ($item['product_id'] ?? 0)] ?? null,
            NavigationPayload::PROPERTY => 'map-pin',
            NavigationPayload::SECTION => ($item['section'] ?? null) === 'enquire' ? 'chat-bubble-left-right' : null,
            default => null,
        };
    }

    /**
     * Where each of the given workspace types leads while it is shown: its page while it has an offering on
     * the website (as WorkspaceCatalogue::categoryWithPage); otherwise, while the enquiry form is open, the
     * form with the type chosen, as the workspaces page does (OD-145); otherwise its own card on the
     * workspaces page, so every shown type keeps its place in the menu (OD-174). A hidden type leads nowhere.
     *
     * @param  list<int>  $productIds
     * @return array<int, string>
     */
    private function categoryPaths(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = Product::query()->whereKey(array_unique($productIds))->shown()
            ->withExists(['offerings as has_page' => fn ($query) => $query->publiclyListed()])
            ->get(['id', 'slug']);
        $enquiryOpen = $products->contains('has_page', false) && app(EnquiryForm::class)->isOpen();

        return $products
            ->mapWithKeys(fn (Product $product): array => [$product->getKey() => match (true) {
                (bool) $product->has_page => '/workspaces/'.$product->slug,
                $enquiryOpen => '/enquire?workspace='.$product->slug,
                default => '/workspaces#'.$product->slug,
            }])
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
