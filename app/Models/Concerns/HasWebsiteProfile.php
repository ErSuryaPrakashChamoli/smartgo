<?php

namespace App\Models\Concerns;

use App\Enums\Content\MediaOrigin;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SeoMetadata;
use App\Services\Content\ContentRules;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What property and offering profiles share (FR-CMS-6): photos of their own property only, search-engine
 * fields that go live with the version, and the check that the property can still be shown when a
 * version is submitted and again, under the publishing lock, when it is published.
 *
 * Photos must be SmartGro or commissioned photography of this property, with a valid licence: concept
 * visuals, renders and stock photos never pass for a property (BR-MED-2), and a photo of another
 * property never appears here.
 */
trait HasWebsiteProfile
{
    use PublishesRevisionFields {
        PublishesRevisionFields::prepareRevisionPayload as prepareRevisionFields;
    }

    public const int MAX_PHOTOS = 12;

    /**
     * Media origins accepted as photos of a property.
     *
     * @var list<MediaOrigin>
     */
    public const array PHOTO_ORIGINS = [MediaOrigin::ClientPhotography, MediaOrigin::Commissioned];

    /**
     * Reasons the profile cannot be shown, read with the property locked; empty when it can.
     *
     * @return list<string>
     */
    abstract protected function publicationBlockers(): array;

    /**
     * The live search-engine metadata, written when a version is published.
     *
     * @return MorphOne<SeoMetadata, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    protected function payloadOnlyFields(): array
    {
        return ['seo_title', 'seo_description'];
    }

    public function toRevisionPayload(): array
    {
        $seo = $this->seo()->first();

        return [...$this->only($this->revisionFields()), 'seo_title' => $seo?->meta_title, 'seo_description' => $seo?->meta_description];
    }

    protected function payloadForValidation(array $payload): array
    {
        $gallery = $payload['gallery_asset_ids'] ?? [];

        return [...$payload, 'gallery_asset_ids' => is_array($gallery) ? array_values($gallery) : $gallery];
    }

    /**
     * @return array<string, mixed>
     */
    protected function photoAndSeoRules(): array
    {
        $rules = app(ContentRules::class);
        $photo = ['nullable', 'integer', Rule::exists('media_assets', 'id'), $this->photoRule()];

        return [
            'hero_asset_id' => $photo,
            'gallery_asset_ids' => ['nullable', 'array', 'list', 'max:'.self::MAX_PHOTOS],
            'gallery_asset_ids.*' => ['distinct', ...$photo],
            'seo_title' => $rules->plainText(false, 70),
            'seo_description' => $rules->plainText(false, 160),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function photoAndSeoMessages(): array
    {
        return [
            'gallery_asset_ids.max' => 'Choose at most :max photos.',
            'gallery_asset_ids.*.distinct' => 'Each photo can appear once.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function photoAndSeoAttributes(): array
    {
        return ['hero_asset_id' => 'main photo', 'gallery_asset_ids' => 'photos', 'gallery_asset_ids.*' => 'photo', 'seo_title' => 'search title', 'seo_description' => 'search description'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePhotos(array $payload): array
    {
        return [
            ...$payload,
            'hero_asset_id' => isset($payload['hero_asset_id']) ? (int) $payload['hero_asset_id'] : null,
            'gallery_asset_ids' => array_map(intval(...), $payload['gallery_asset_ids'] ?? []),
        ];
    }

    public function prepareRevisionPayload(array $payload, bool $forSubmission): array
    {
        $prepared = $this->prepareRevisionFields($payload, $forSubmission);

        if ($forSubmission && ($blockers = $this->publicationBlockers()) !== []) {
            throw ValidationException::withMessages(['publication' => $blockers]);
        }

        return $prepared;
    }

    /**
     * Writes the version's search-engine fields; the page is indexable once published.
     */
    protected function revisionPublished(Revision $revision): void
    {
        $this->seo()->updateOrCreate([], [
            'meta_title' => $revision->payload['seo_title'] ?? null,
            'meta_description' => $revision->payload['seo_description'] ?? null,
            'robots' => SeoMetadata::INDEX,
        ]);
    }

    /**
     * Why the property itself cannot be shown: its state (BR-AV-6) or an incomplete address (BR-PROP-3).
     *
     * @return list<string>
     */
    protected function propertyBlockers(Property $property): array
    {
        $building = $property->primaryBuilding()->first();

        return array_values(array_filter([
            $property->operational_status->allowsListing($property->opens_on !== null) ? null : match (true) {
                in_array($property->operational_status, $property->operational_status::listableWithOpeningDate(), true) => 'Record the property\'s opening date first: a property that is not open yet is shown as "Opening {month year}".',
                default => 'The property is '.strtolower($property->operational_status->label()).', so it cannot be shown on the website.',
            },
            filled($building?->address_line_1) && filled($building?->locality) ? null : 'Complete the property\'s address (address line 1 and sector or locality) first.',
        ]));
    }

    /**
     * Only real photography of this property with a valid licence (BR-MED-2, BR-MED-3).
     */
    private function photoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $asset = is_numeric($value) ? MediaAsset::query()->find((int) $value) : null;

            if ($asset === null) {
                return;
            }

            if (! in_array($asset->origin, self::PHOTO_ORIGINS, true)) {
                $fail('Use SmartGro or commissioned photography of the property. Concept visuals, renders and stock photos cannot be shown as the property.');
            } elseif ((int) $asset->depicts_property_id !== (int) $this->owningProperty()->getKey()) {
                $fail('Choose a photo uploaded for this property.');
            } elseif ($asset->isLicenceExpired()) {
                $fail('This image\'s licence has expired, so it cannot be used.');
            }
        };
    }
}
