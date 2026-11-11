<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Inventory\SpaceStatus;
use App\Enums\Properties\PropertyStatus;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentRules;
use Closure;
use Database\Factories\SpaceListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * A space the website lists under Spaces in the main menu (OD-177), at a location and of a space type, with its own
 * page at /spaces/{slug}: its name, a summary, details, optional seats and features, and an optional photo (OD-177 e; photography
 * of its location, or a labelled stock photo where sample content is allowed). It may name a space in the
 * inventory (`space_id`, "use the original space"), which must be at the same location and of the same type; the
 * inventory itself is never changed here. Example content that SmartGro has not confirmed is marked `is_sample`, and
 * is submitted and published only where sample content is allowed (OD-149). Versions are approved and published by
 * different people with the website-content permissions; switching a listing off takes it off the website at once
 * (OD-98), and switching it back on needs a new version, approved and published as usual. A listing shows only while
 * its location may be listed (open, or opening with a date: BR-AV-6). A database without the table shows no
 * listings (isAvailable()).
 */
class SpaceListing extends Model implements Unpublishable
{
    /** @use HasFactory<SpaceListingFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * A space type's own icon in the menu, by space type key; the menu editor can choose another.
     *
     * @var array<string, string>
     */
    public const array TYPE_ICONS = [
        'private_cabin' => 'lock-closed',
        'desk_zone_dedicated' => 'computer-desktop',
        'desk_zone_flexible' => 'arrows-right-left',
        'meeting_room' => 'user-group',
        'conference_training_room' => 'presentation-chart-bar',
        'office_suite' => 'building-office-2',
    ];

    /**
     * At most this many features per listing.
     */
    public const int MAX_FEATURES = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'media_asset_id' => 'integer',
            'is_sample' => 'boolean',
            'seats' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Whether this database has space listings. Asked once a request.
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable((new self)->getTable()));
    }

    /**
     * Whether listings can hold a photo on this database (OD-177 e). Asked once a request.
     */
    public static function hasPhotos(): bool
    {
        return once(fn (): bool => self::isAvailable() && Schema::hasColumn((new self)->getTable(), 'media_asset_id'));
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<SpaceType, $this>
     */
    public function spaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class);
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * Published listings at a location that may be listed (open, or opening with a date).
     *
     * @param  Builder<self>  $query
     */
    public function scopeShown(Builder $query): void
    {
        $query->published()->whereHas('property', fn (Builder $property) => $property->where(fn (Builder $state) => $state
            ->whereIn('operational_status', PropertyStatus::listableWithoutOpeningDate())
            ->orWhere(fn (Builder $opening) => $opening->whereIn('operational_status', PropertyStatus::listableWithOpeningDate())->whereNotNull('opens_on'))));
    }

    public function path(): ?string
    {
        return $this->isPublished() && filled($this->slug) ? '/spaces/'.$this->slug : null;
    }

    /**
     * "SmartGro A-70" and "Sector 2, Noida", for the menu and the page.
     *
     * @return array{name: string, area: string}
     */
    public function locationLabel(): array
    {
        return [
            'name' => 'SmartGro '.$this->property?->name,
            'area' => collect([$this->property?->microMarket?->name, $this->property?->city?->name])->filter()->join(', '),
        ];
    }

    public function contentLabel(): string
    {
        return 'space';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['property_id', 'space_type_id', 'space_id', 'name', 'slug', 'summary', 'details', 'seats', 'features', ...(self::hasPhotos() ? ['media_asset_id'] : []), 'is_sample', 'sort_order'];
    }

    protected function payloadForValidation(array $payload): array
    {
        return [
            ...$payload,
            'features' => is_array($payload['features'] ?? null) ? array_values(array_filter($payload['features'], fn (mixed $feature): bool => filled($feature))) : ($payload['features'] ?? null),
        ];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);
        $required = $forSubmission ? 'required' : 'nullable';

        return [
            'rules' => [
                'property_id' => [$required, 'integer', Rule::exists('properties', 'id')],
                'space_type_id' => [$required, 'integer', Rule::exists('space_types', 'id')],
                'space_id' => ['nullable', 'integer', $this->inventorySpaceRule($payload)],
                'name' => $rules->plainText($forSubmission, 80),
                'slug' => $rules->slug($forSubmission, $this->getTable(), $this->getKey()),
                'summary' => $rules->plainText($forSubmission, 200),
                'details' => $rules->plainText(false, 3000),
                'seats' => ['nullable', 'integer', 'between:1,9999'],
                'features' => ['nullable', 'array', 'list', 'max:'.self::MAX_FEATURES],
                'features.*' => ['string', 'max:80', 'not_regex:/[<>]/'],
                ...(self::hasPhotos() ? ['media_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id'), $this->photoRule($payload)]] : []),
                'is_sample' => ['nullable', 'boolean', ...($forSubmission ? [$this->sampleAllowedRule()] : [])],
                'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            ],
            'messages' => [
                ...$rules->messages(),
                'slug.regex' => 'Use lowercase letters, numbers and hyphens (for example a-70-cabin-1).',
                'features.max' => 'Add at most '.self::MAX_FEATURES.' features.',
                'features.*.max' => 'Keep each feature under 80 characters.',
                'features.*.not_regex' => 'Use plain text: < and > are not allowed.',
                'media_asset_id.exists' => 'The chosen photo is no longer in the media library.',
            ],
            'attributes' => ['property_id' => 'location', 'space_type_id' => 'space type', 'space_id' => 'inventory space', 'slug' => 'web address', 'media_asset_id' => 'photo'],
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [
            ...$payload,
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : null,
            'space_type_id' => isset($payload['space_type_id']) ? (int) $payload['space_type_id'] : null,
            'space_id' => isset($payload['space_id']) ? (int) $payload['space_id'] : null,
            'details' => filled($payload['details'] ?? null) ? trim((string) $payload['details']) : null,
            'seats' => isset($payload['seats']) ? (int) $payload['seats'] : null,
            'features' => array_values(array_map(fn (string $feature): string => trim($feature), $payload['features'] ?? [])),
            ...(self::hasPhotos() ? ['media_asset_id' => filled($payload['media_asset_id'] ?? null) ? (int) $payload['media_asset_id'] : null] : []),
            'is_sample' => (bool) ($payload['is_sample'] ?? false),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    /**
     * An inventory space, when named, must exist, be in use and be at the listing's location and of its type.
     *
     * @param  array<string, mixed>  $payload
     */
    private function inventorySpaceRule(array $payload): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($payload): void {
            if (! Space::isAvailable()) {
                $fail('This website has no inventory yet, so a listing cannot name an inventory space.');

                return;
            }

            $matches = Space::query()->whereKey((int) $value)
                ->where('property_id', (int) ($payload['property_id'] ?? 0))
                ->where('space_type_id', (int) ($payload['space_type_id'] ?? 0))
                ->where('operational_status', '!=', SpaceStatus::Decommissioned->value)
                ->exists();

            if (! $matches) {
                $fail('Choose an inventory space at this location and of this space type, or leave it empty.');
            }
        };
    }

    /**
     * As a location's photos (HasWebsiteProfile::photoRule()): photography of the listing's location with a valid
     * licence (BR-MED-2, BR-MED-3), or, where sample content is allowed, a licensed stock photo (OD-176), which the
     * page labels as not the location.
     *
     * @param  array<string, mixed>  $payload
     */
    private function photoRule(array $payload): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($payload): void {
            $asset = is_numeric($value) ? MediaAsset::query()->find((int) $value) : null;

            if ($asset === null) {
                return;
            }

            $isSampleStock = in_array($asset->origin, PropertyProfile::SAMPLE_PHOTO_ORIGINS, true);

            if ($isSampleStock && ! config('backoffice.sample_content')) {
                $fail('Stock photos can only be sample content, which is not allowed here. Use SmartGro or commissioned photography of the location.');
            } elseif (! $isSampleStock && ! in_array($asset->origin, PropertyProfile::PHOTO_ORIGINS, true)) {
                $fail('Use SmartGro or commissioned photography of the location. Concept visuals and renders cannot be shown as the space.');
            } elseif (! $isSampleStock && (int) $asset->depicts_property_id !== (int) ($payload['property_id'] ?? 0)) {
                $fail('Choose a photo uploaded for this space\'s location.');
            } elseif ($asset->isLicenceExpired()) {
                $fail('This image\'s licence has expired, so it cannot be used.');
            }
        };
    }

    /**
     * Example content can be submitted and published only where sample content is allowed (OD-149). Drafts keep the
     * mark, so they can be finished.
     */
    private function sampleAllowedRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ((bool) $value && ! config('backoffice.sample_content')) {
                $fail('This website does not publish example spaces. Write SmartGro\'s own details and untick "Example space" first.');
            }
        };
    }
}
