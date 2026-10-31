<?php

namespace App\Services\Content;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Services\Website\SiteSections;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Server-side validation of a menu revision (FR-CMS-4). Items link to a page, a fixed part of the
 * website or a property, each picked from a list, or to an https address on another website; group
 * items are headings whose links are their children. Labels are plain text. Each menu's size and shape
 * come from NavigationMenu::STRUCTURES. Inside a main-menu drop-down, links and sub-groups may also carry a
 * plain-text description and an icon from NavigationItem::ICONS (OD-149); elsewhere both are dropped.
 */
class NavigationPayload
{
    public const string GROUP = 'group';

    public const string SECTION = 'section';

    public const string PROPERTY = 'property';

    public const string CATEGORY = 'category';

    /**
     * The kinds of link an item or a group's link can be.
     *
     * @var list<string>
     */
    public const array LINKS = [ContentLink::TO_PAGE, self::SECTION, self::PROPERTY, self::CATEGORY, ContentLink::TO_URL];

    public function __construct(private ContentLink $contentLink) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{items: list<array{label: string|null, type: string, page_id: int|null, section: string|null, property_id: int|null, product_id: int|null, url: string|null, children: list<array<string, mixed>>}>} children carry `description` and `icon` where the menu offers them
     *
     * @throws ValidationException
     */
    public function validate(NavigationMenu $menu, array $payload, bool $forSubmission): array
    {
        $structure = $menu->structure();
        $payload['items'] = $this->asList($payload['items'] ?? []);

        foreach ($payload['items'] as $index => $item) {
            if (is_array($item) && array_key_exists('children', $item)) {
                $payload['items'][$index]['children'] = $this->asList($item['children']);

                foreach ($payload['items'][$index]['children'] as $childIndex => $child) {
                    if (is_array($child) && array_key_exists('children', $child)) {
                        $payload['items'][$index]['children'][$childIndex]['children'] = $this->asList($child['children']);
                    }
                }
            }
        }

        $required = $forSubmission ? 'required' : 'nullable';
        $topLevelTypes = match ($structure['groups']) {
            'none' => self::LINKS,
            'allowed' => [...self::LINKS, self::GROUP],
            'only' => [self::GROUP],
        };
        // A menu whose items may be groups may also hold sub-groups inside a group, one level deep: a
        // drop-down then opens with headings, each showing its own links (OD-145).
        $childTypes = $structure['groups'] === 'allowed' ? [...self::LINKS, self::GROUP] : self::LINKS;
        $details = $menu->offersDetails();
        $keys = 'label,type,page_id,section,property_id,product_id,url,description,icon';

        $validated = Validator::make($payload, [
            'items' => ['present', 'array', 'list', 'max:'.$structure['max_items'], $this->groupsRule($forSubmission)],
            'items.*' => ["array:{$keys},children"],
            ...$this->linkRules('items.*', $topLevelTypes, $required, false),
            'items.*.children' => ['nullable', 'array', 'list', 'max:'.$structure['max_children'], $this->groupsRule($forSubmission)],
            'items.*.children.*' => ["array:{$keys},children"],
            ...$this->linkRules('items.*.children.*', $childTypes, $required, $details),
            'items.*.children.*.children' => ['nullable', 'array', 'list', 'max:'.$structure['max_children']],
            'items.*.children.*.children.*' => ["array:{$keys}"],
            ...$this->linkRules('items.*.children.*.children.*', self::LINKS, $required, $details),
        ], [
            'items.max' => 'This menu can have at most :max items.',
            'items.*.children.max' => 'A group can have at most :max links.',
            'items.*.children.*.children.max' => 'A sub-group can have at most :max links.',
            'items.*.type.in' => $structure['groups'] === 'only' ? 'Every item in this menu is a heading with links.' : 'Choose where this item goes.',
            'items.*.children.*.icon.in' => 'Choose an icon from the list.',
            'items.*.children.*.children.*.icon.in' => 'Choose an icon from the list.',
            'items.*.children.*.description.not_regex' => 'Use plain text: < and > are not allowed.',
            'items.*.children.*.children.*.description.not_regex' => 'Use plain text: < and > are not allowed.',
        ], [
            'items.*.label' => 'item label',
            'items.*.page_id' => 'page',
            'items.*.section' => 'part of the website',
            'items.*.property_id' => 'location',
            'items.*.product_id' => 'workspace type',
            'items.*.url' => 'web address',
            'items.*.children.*.label' => 'link label',
            'items.*.children.*.page_id' => 'page',
            'items.*.children.*.section' => 'part of the website',
            'items.*.children.*.property_id' => 'location',
            'items.*.children.*.product_id' => 'workspace type',
            'items.*.children.*.url' => 'web address',
            'items.*.children.*.description' => 'description',
            'items.*.children.*.icon' => 'icon',
            'items.*.children.*.children.*.label' => 'link label',
            'items.*.children.*.children.*.page_id' => 'page',
            'items.*.children.*.children.*.section' => 'part of the website',
            'items.*.children.*.children.*.property_id' => 'location',
            'items.*.children.*.children.*.product_id' => 'workspace type',
            'items.*.children.*.children.*.url' => 'web address',
            'items.*.children.*.children.*.description' => 'description',
            'items.*.children.*.children.*.icon' => 'icon',
        ])->validate();

        return ['items' => array_map(fn (array $item): array => [
            ...$this->normalizeLink($item, false),
            'children' => $item['type'] === self::GROUP ? array_map(fn (array $child): array => $child['type'] === self::GROUP
                ? [...$this->normalizeLink($child, $details), 'children' => array_map(fn (array $link): array => $this->normalizeLink($link, $details), $child['children'] ?? [])]
                : $this->normalizeLink($child, $details), $item['children'] ?? []) : [],
        ], $validated['items'])];
    }

    /**
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    private function linkRules(string $prefix, array $types, string $required, bool $withDetails): array
    {
        return [
            ...($withDetails ? [
                "{$prefix}.description" => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
                "{$prefix}.icon" => ['nullable', 'string', Rule::in(array_keys(NavigationItem::ICONS))],
            ] : []),
            "{$prefix}.label" => [$required, 'string', 'max:40', 'not_regex:/[<>]/'],
            "{$prefix}.type" => ['required', 'string', Rule::in($types)],
            "{$prefix}.page_id" => ['nullable', 'integer', Rule::exists('pages', 'id'), "required_if:{$prefix}.type,".ContentLink::TO_PAGE],
            "{$prefix}.section" => ['nullable', 'string', Rule::in(array_keys(SiteSections::SECTIONS)), "required_if:{$prefix}.type,".self::SECTION],
            "{$prefix}.property_id" => ['nullable', 'integer', Rule::exists('properties', 'id'), "required_if:{$prefix}.type,".self::PROPERTY],
            "{$prefix}.product_id" => ['nullable', 'integer', Rule::exists('products', 'id'), "required_if:{$prefix}.type,".self::CATEGORY],
            "{$prefix}.url" => ['nullable', 'string', 'max:2048', $this->contentLink->safeUrlRule(), "required_if:{$prefix}.type,".ContentLink::TO_URL],
        ];
    }

    /**
     * Only a group holds links, and a group must hold at least one before it can be submitted.
     */
    private function groupsRule(bool $forSubmission): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($forSubmission): void {
            foreach (is_array($value) ? $value : [] as $index => $item) {
                $isGroup = is_array($item) && ($item['type'] ?? null) === self::GROUP;
                $hasChildren = is_array($item) && filled($item['children'] ?? []);

                if (! $isGroup && $hasChildren) {
                    $fail('Item '.($index + 1).' is a link, so it cannot hold other links. Make it a group instead.');
                } elseif ($isGroup && ! $hasChildren && $forSubmission) {
                    $fail('Group '.($index + 1).' needs at least one link.');
                }
            }
        };
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array{label: string|null, type: string, page_id: int|null, section: string|null, property_id: int|null, product_id: int|null, url: string|null, description?: string|null, icon?: string|null}
     */
    private function normalizeLink(array $link, bool $withDetails): array
    {
        return [
            'label' => $link['label'] ?? null,
            'type' => $link['type'],
            'page_id' => $link['type'] === ContentLink::TO_PAGE ? (int) $link['page_id'] : null,
            'section' => $link['type'] === self::SECTION ? $link['section'] : null,
            'property_id' => $link['type'] === self::PROPERTY ? (int) $link['property_id'] : null,
            'product_id' => $link['type'] === self::CATEGORY ? (int) $link['product_id'] : null,
            'url' => $link['type'] === ContentLink::TO_URL ? $link['url'] : null,
            ...($withDetails ? ['description' => filled($link['description'] ?? null) ? trim($link['description']) : null, 'icon' => $link['icon'] ?? null] : []),
        ];
    }

    /**
     * @return list<mixed>|mixed
     */
    private function asList(mixed $items): mixed
    {
        return is_array($items) ? array_values($items) : $items;
    }
}
