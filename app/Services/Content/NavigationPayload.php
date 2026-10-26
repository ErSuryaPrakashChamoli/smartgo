<?php

namespace App\Services\Content;

use App\Models\NavigationMenu;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Server-side validation of a menu revision (FR-CMS-4). Items link to a page picked from the list or to
 * an https address on another website; group items are headings whose links are their children. Labels
 * are plain text. Each menu's size and shape come from NavigationMenu::STRUCTURES.
 */
class NavigationPayload
{
    public const string GROUP = 'group';

    public function __construct(private ContentLink $contentLink) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{items: list<array{label: string|null, type: string, page_id: int|null, url: string|null, children: list<array<string, mixed>>}>}
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
            }
        }

        $required = $forSubmission ? 'required' : 'nullable';
        $topLevelTypes = match ($structure['groups']) {
            'none' => [ContentLink::TO_PAGE, ContentLink::TO_URL],
            'allowed' => [ContentLink::TO_PAGE, ContentLink::TO_URL, self::GROUP],
            'only' => [self::GROUP],
        };

        $validated = Validator::make($payload, [
            'items' => ['present', 'array', 'list', 'max:'.$structure['max_items'], $this->groupsRule($forSubmission)],
            'items.*' => ['array:label,type,page_id,url,children'],
            ...$this->linkRules('items.*', $topLevelTypes, $required),
            'items.*.children' => ['nullable', 'array', 'list', 'max:'.$structure['max_children']],
            'items.*.children.*' => ['array:label,type,page_id,url'],
            ...$this->linkRules('items.*.children.*', [ContentLink::TO_PAGE, ContentLink::TO_URL], $required),
        ], [
            'items.max' => 'This menu can have at most :max items.',
            'items.*.children.max' => 'A group can have at most :max links.',
            'items.*.type.in' => $structure['groups'] === 'only' ? 'Every item in this menu is a heading with links.' : 'Choose where this item goes.',
        ], [
            'items.*.label' => 'item label',
            'items.*.page_id' => 'page',
            'items.*.url' => 'web address',
            'items.*.children.*.label' => 'link label',
            'items.*.children.*.page_id' => 'page',
            'items.*.children.*.url' => 'web address',
        ])->validate();

        return ['items' => array_map(fn (array $item): array => [
            ...$this->normalizeLink($item),
            'children' => $item['type'] === self::GROUP ? array_map($this->normalizeLink(...), $item['children'] ?? []) : [],
        ], $validated['items'])];
    }

    /**
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    private function linkRules(string $prefix, array $types, string $required): array
    {
        return [
            "{$prefix}.label" => [$required, 'string', 'max:40', 'not_regex:/[<>]/'],
            "{$prefix}.type" => ['required', 'string', Rule::in($types)],
            "{$prefix}.page_id" => ['nullable', 'integer', Rule::exists('pages', 'id'), "required_if:{$prefix}.type,".ContentLink::TO_PAGE],
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
     * @return array{label: string|null, type: string, page_id: int|null, url: string|null}
     */
    private function normalizeLink(array $link): array
    {
        return [
            'label' => $link['label'] ?? null,
            'type' => $link['type'],
            'page_id' => $link['type'] === ContentLink::TO_PAGE ? (int) $link['page_id'] : null,
            'url' => $link['type'] === ContentLink::TO_URL ? $link['url'] : null,
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
