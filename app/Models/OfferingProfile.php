<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasWebsiteProfile;
use App\Models\Contracts\BelongsToProperty;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentRules;
use Database\Factories\OfferingProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The marketing face of one workspace offering at one property (FR-CMS-6, IA §3.1 item 3): headline,
 * description, what is and is not included, photos of the property and search-engine fields. It never
 * holds capacity, availability or price (BR-SOT-1, BR-SOT-3); the website says these are confirmed on
 * request until inventory and approved prices exist. Changes go through draft, approval and publication
 * (ADR-0005), and it can be taken down at once (OD-98). A Property Manager writes drafts only for
 * offerings of assigned properties and never publishes.
 */
class OfferingProfile extends Model implements BelongsToProperty, Unpublishable
{
    /** @use HasFactory<OfferingProfileFactory> */
    use HasFactory, HasRevisions, HasWebsiteProfile;

    public const int MAX_LIST_ITEMS = 12;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['included' => 'array', 'not_included' => 'array', 'gallery_asset_ids' => 'array', 'published_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Offering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function owningProperty(): Property
    {
        return $this->offering->property;
    }

    public function contentLabel(): string
    {
        return 'workspace offering';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewProfiles, 'edit' => Capability::EditProfiles, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['headline', 'description', 'included', 'not_included', 'hero_asset_id', 'gallery_asset_ids'];
    }

    protected function payloadForValidation(array $payload): array
    {
        $list = fn (mixed $items): mixed => is_array($items) ? array_values(array_filter($items, fn (mixed $item): bool => ! is_string($item) || trim($item) !== '')) : $items;

        $gallery = $payload['gallery_asset_ids'] ?? [];

        return [
            ...$payload,
            'included' => $list($payload['included'] ?? []),
            'not_included' => $list($payload['not_included'] ?? []),
            'gallery_asset_ids' => is_array($gallery) ? array_values($gallery) : $gallery,
        ];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);

        return [
            'rules' => [
                'headline' => $rules->plainText($forSubmission, 120),
                'description' => $rules->plainText($forSubmission, 1200),
                'included' => ['nullable', 'array', 'list', 'max:'.self::MAX_LIST_ITEMS],
                'included.*' => ['string', 'max:80', 'not_regex:/[<>]/', 'distinct'],
                'not_included' => ['nullable', 'array', 'list', 'max:'.self::MAX_LIST_ITEMS],
                'not_included.*' => ['string', 'max:80', 'not_regex:/[<>]/', 'distinct'],
                ...$this->photoAndSeoRules(),
            ],
            'messages' => [
                ...$rules->messages(),
                ...$this->photoAndSeoMessages(),
                'included.max' => 'List at most :max items.',
                'not_included.max' => 'List at most :max items.',
                'included.*.not_regex' => 'Use plain text: < and > are not allowed.',
                'not_included.*.not_regex' => 'Use plain text: < and > are not allowed.',
            ],
            'attributes' => ['included' => 'what is included', 'not_included' => 'what is not included', 'included.*' => 'item', 'not_included.*' => 'item', ...$this->photoAndSeoAttributes()],
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [
            ...$this->normalizePhotos($payload),
            'included' => array_values(array_map(trim(...), $payload['included'] ?? [])),
            'not_included' => array_values(array_map(trim(...), $payload['not_included'] ?? [])),
        ];
    }

    /**
     * An offering can be published while operations staff keep it active, its category is active and
     * its property's state and address allow the property to be shown. It appears on the website once
     * the property's own profile is published too.
     */
    protected function publicationBlockers(): array
    {
        $property = Property::query()->lockForUpdate()->findOrFail($this->offering->property_id);
        $offering = Offering::query()->with('product')->lockForUpdate()->findOrFail($this->offering_id);

        return [
            ...$this->propertyBlockers($property),
            ...($offering->is_active ? [] : ['Operations staff no longer record this workspace category at the property.']),
            ...($offering->product->is_active ? [] : ['This workspace category is switched off.']),
        ];
    }
}
