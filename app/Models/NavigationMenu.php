<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use App\Services\Content\ContentLink;
use App\Services\Content\NavigationPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the website's fixed menus (FR-CMS-4). The row and its items hold only the published menu;
 * changes are revisions that go through draft, approval and publication like pages (ADR-0005,
 * SECURITY_MODEL §11.5).
 */
class NavigationMenu extends Model implements Revisionable
{
    use HasRevisions;

    /**
     * What each menu may hold. `groups`: `none` (links only), `allowed` (links, or headings that open a
     * drop-down) or `only` (every item is a heading with links, as footer columns are).
     *
     * @var array<string, array{max_items: int, groups: 'none'|'allowed'|'only', max_children: int, purpose: string}>
     */
    public const array STRUCTURES = [
        'main' => ['max_items' => 7, 'groups' => 'allowed', 'max_children' => 12, 'purpose' => 'Links across the top of every page. A group opens a drop-down list of links.'],
        'actions' => ['max_items' => 2, 'groups' => 'none', 'max_children' => 0, 'purpose' => 'Up to two buttons in the header and in the action bar on phones. The last one is the main button.'],
        'footer' => ['max_items' => 4, 'groups' => 'only', 'max_children' => 10, 'purpose' => 'Footer columns. Each column is a heading with its links.'],
        'legal' => ['max_items' => 8, 'groups' => 'none', 'max_children' => 0, 'purpose' => 'Small links at the bottom of every page, such as privacy and terms.'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<NavigationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    public function isPublished(): bool
    {
        return $this->published_revision_id !== null;
    }

    /**
     * @return array{max_items: int, groups: 'none'|'allowed'|'only', max_children: int, purpose: string}
     */
    public function structure(): array
    {
        return self::STRUCTURES[$this->handle];
    }

    public function revisionCapabilities(): array
    {
        return [
            'view' => Capability::ViewContent,
            'edit' => Capability::EditContent,
            'approve' => Capability::ApproveContent,
            'publish' => Capability::PublishContent,
        ];
    }

    /**
     * The live menu as a payload: top-level items in order, each group with its links.
     */
    public function toRevisionPayload(): array
    {
        $items = $this->items()->get();
        $children = $items->whereNotNull('parent_id')->groupBy('parent_id');

        return [
            'items' => $items->whereNull('parent_id')->values()->map(fn (NavigationItem $item): array => [
                ...$this->itemPayload($item),
                'children' => ($children->get($item->getKey()) ?? collect())->values()->map(fn (NavigationItem $child): array => $this->itemPayload($child))->all(),
            ])->all(),
        ];
    }

    public function prepareRevisionPayload(array $payload, bool $forSubmission): array
    {
        return app(NavigationPayload::class)->validate($this, $payload, $forSubmission);
    }

    /**
     * Replaces the live items with the version's items, inside the publishing transaction.
     */
    public function applyPublishedRevision(Revision $revision): void
    {
        NavigationItem::query()->where('navigation_menu_id', $this->getKey())->whereNotNull('parent_id')->delete();
        NavigationItem::query()->where('navigation_menu_id', $this->getKey())->delete();

        foreach (array_values($revision->payload['items'] ?? []) as $position => $item) {
            $parent = $this->createItem($item, $position, null);

            foreach (array_values($item['children'] ?? []) as $childPosition => $child) {
                $this->createItem($child, $childPosition, $parent);
            }
        }

        $this->forceFill(['published_revision_id' => $revision->getKey(), 'published_at' => $revision->published_at])->save();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createItem(array $item, int $position, ?NavigationItem $parent): NavigationItem
    {
        $linkType = match ($item['type']) {
            ContentLink::TO_PAGE => NavigationItem::LINK_PAGE,
            NavigationPayload::PROPERTY => NavigationItem::LINK_PROPERTY,
            NavigationPayload::SECTION => NavigationItem::LINK_SECTION,
            ContentLink::TO_URL => NavigationItem::LINK_EXTERNAL,
            default => NavigationItem::LINK_GROUP,
        };

        return $this->items()->forceCreate([
            'parent_id' => $parent?->getKey(),
            'label' => $item['label'],
            'link_type' => $linkType,
            'linkable_type' => match ($linkType) {
                NavigationItem::LINK_PAGE => (new Page)->getMorphClass(),
                NavigationItem::LINK_PROPERTY => (new Property)->getMorphClass(),
                default => null,
            },
            'linkable_id' => match ($linkType) {
                NavigationItem::LINK_PAGE => $item['page_id'],
                NavigationItem::LINK_PROPERTY => $item['property_id'],
                default => null,
            },
            'section' => $linkType === NavigationItem::LINK_SECTION ? $item['section'] : null,
            'url' => $linkType === NavigationItem::LINK_EXTERNAL ? $item['url'] : null,
            'sort_order' => $position,
        ]);
    }

    /**
     * @return array{label: string, type: string, page_id: int|null, section: string|null, property_id: int|null, url: string|null}
     */
    private function itemPayload(NavigationItem $item): array
    {
        return [
            'label' => $item->label,
            'type' => match ($item->link_type) {
                NavigationItem::LINK_PAGE => ContentLink::TO_PAGE,
                NavigationItem::LINK_PROPERTY => NavigationPayload::PROPERTY,
                NavigationItem::LINK_SECTION => NavigationPayload::SECTION,
                NavigationItem::LINK_EXTERNAL => ContentLink::TO_URL,
                default => NavigationPayload::GROUP,
            },
            'page_id' => $item->link_type === NavigationItem::LINK_PAGE ? $item->linkable_id : null,
            'section' => $item->section,
            'property_id' => $item->link_type === NavigationItem::LINK_PROPERTY ? $item->linkable_id : null,
            'url' => $item->url,
        ];
    }
}
