<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Content\MediaOrigin;
use App\Enums\Twin\AnchorType;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasWebsiteProfile;
use App\Models\Contracts\BelongsToProperty;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentRules;
use Closure;
use Database\Factories\PropertyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * The marketing face of a property (FR-CMS-6): headline, summary, directions and its "Getting here"
 * details (nearest metro station and walk, buses, parking, accessibility; FR-DISC-7), the location's
 * own phone, WhatsApp and email (empty means SmartGro's main contact details are shown), photos and
 * search-engine fields. It never holds capacity, status, availability or price, which stay on the
 * operational records. Its publication is the property's website publication: a property is on the
 * website only while this profile is published and the property's state allows it (BR-AV-6). Changes
 * go through draft, approval and publication (ADR-0005); taking it down removes the property from the
 * website at once (OD-98). A Property Manager writes drafts only for assigned properties and never
 * publishes.
 *
 * Where the database has the columns (OD-181), the profile also holds what the page's "Explore" section shows: one
 * of the property's 3D models with its guided views (its own camera positions, each given a title; no coordinates
 * are ever typed, FR-3D-2) and floor plans (BR-3D-5). A model always carries the notice its capture type requires,
 * which nobody can remove (BR-3D-2). A floor plan is SmartGro's own or a commissioned plan uploaded for this
 * property; a render or concept may only be an example, only where sample content is allowed, and the page labels
 * it "Example floor plan — not SmartGro's layout".
 */
class PropertyProfile extends Model implements BelongsToProperty, Unpublishable
{
    /** @use HasFactory<PropertyProfileFactory> */
    use HasFactory, HasRevisions, HasWebsiteProfile {
        HasWebsiteProfile::payloadForValidation as websiteProfilePayloadForValidation;
    }

    /**
     * The "Getting here" details, in the order the property page shows them.
     *
     * @var list<string>
     */
    public const array GETTING_HERE_FIELDS = ['nearest_metro', 'metro_walk_minutes', 'buses', 'parking', 'accessibility'];

    /**
     * The 3D walkthrough and floor plan fields (OD-181), present only where the database has their columns.
     *
     * @var list<string>
     */
    public const array EXPLORE_FIELDS = ['twin_model_id', 'twin_tour_stops', 'floor_plans'];

    public const int MAX_TOUR_STOPS = 12;

    public const int MAX_FLOOR_PLANS = 10;

    /**
     * Media origins accepted as a floor plan of the property: SmartGro's own or a commissioned plan.
     *
     * @var list<MediaOrigin>
     */
    public const array FLOOR_PLAN_ORIGINS = [MediaOrigin::ClientPhotography, MediaOrigin::Commissioned];

    /**
     * Media origins accepted only as an example floor plan, where sample content is allowed.
     *
     * @var list<MediaOrigin>
     */
    public const array EXAMPLE_FLOOR_PLAN_ORIGINS = [MediaOrigin::Render, MediaOrigin::AiGeneratedConcept];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gallery_asset_ids' => 'array', 'twin_model_id' => 'integer', 'twin_tour_stops' => 'array', 'floor_plans' => 'array', 'published_at' => 'datetime'];
    }

    /**
     * Whether profiles can hold a 3D walkthrough and floor plans on this database (OD-181). Asked once a request.
     */
    public static function hasExplore(): bool
    {
        return once(fn (): bool => Schema::hasColumns((new self)->getTable(), self::EXPLORE_FIELDS));
    }

    /**
     * The live 3D model; only read where hasExplore() holds.
     *
     * @return BelongsTo<TwinModel, $this>
     */
    public function twinModel(): BelongsTo
    {
        return $this->belongsTo(TwinModel::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owningProperty(): Property
    {
        return $this->property;
    }

    public function contentLabel(): string
    {
        return 'property profile';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewProfiles, 'edit' => Capability::EditProfiles, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['headline', 'summary', 'getting_there', ...self::GETTING_HERE_FIELDS, 'phone', 'whatsapp', 'email', 'hero_asset_id', 'gallery_asset_ids', ...(self::hasExplore() ? self::EXPLORE_FIELDS : [])];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);

        return [
            'rules' => [
                'headline' => $rules->plainText($forSubmission, 120),
                'summary' => $rules->plainText($forSubmission, 600),
                'getting_there' => $rules->plainText(false, 600),
                'nearest_metro' => $rules->plainText(false, 80),
                'metro_walk_minutes' => ['nullable', 'integer', 'between:1,60', Rule::prohibitedIf(blank($payload['nearest_metro'] ?? null))],
                'buses' => $rules->plainText(false, 200),
                'parking' => $rules->plainText(false, 300),
                'accessibility' => $rules->plainText(false, 300),
                ...$rules->contactDetails(),
                ...$this->photoAndSeoRules(),
                ...(self::hasExplore() ? $this->exploreRules($payload) : []),
            ],
            'messages' => [
                ...$rules->messages(), ...$this->photoAndSeoMessages(),
                'metro_walk_minutes.prohibited' => 'Add the nearest metro station first.',
                'twin_model_id.exists' => 'Choose a 3D model uploaded for this property.',
                'twin_tour_stops.prohibited' => 'Choose the 3D model first, then its guided views.',
                'twin_tour_stops.max' => 'Choose at most :max guided views.',
                'twin_tour_stops.*.camera.distinct' => 'Each camera position can be one guided view only.',
                'floor_plans.max' => 'Add at most :max floor plans.',
            ],
            'attributes' => [
                'getting_there' => 'getting there', 'nearest_metro' => 'nearest metro station', 'metro_walk_minutes' => 'walk from the metro', 'whatsapp' => 'WhatsApp number', ...$this->photoAndSeoAttributes(),
                'twin_model_id' => '3D model', 'twin_tour_stops' => 'guided views', 'twin_tour_stops.*.camera' => 'camera position', 'twin_tour_stops.*.title' => 'view title',
                'floor_plans' => 'floor plans', 'floor_plans.*.label' => 'floor', 'floor_plans.*.media_asset_id' => 'floor plan image', 'floor_plans.*.note' => 'note',
            ],
        ];
    }

    protected function payloadForValidation(array $payload): array
    {
        $payload = $this->websiteProfilePayloadForValidation($payload);

        if (! self::hasExplore()) {
            return $payload;
        }

        // The back office's repeaters key their items; the version stores plain lists.
        foreach (['twin_tour_stops', 'floor_plans'] as $list) {
            if (is_array($payload[$list] ?? null)) {
                $payload[$list] = array_values($payload[$list]);
            }
        }

        return $payload;
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        $payload['metro_walk_minutes'] = $payload['metro_walk_minutes'] === null ? null : (int) $payload['metro_walk_minutes'];

        if (self::hasExplore()) {
            $payload['twin_model_id'] = isset($payload['twin_model_id']) ? (int) $payload['twin_model_id'] : null;
            $payload['twin_tour_stops'] = array_map(fn (array $stop): array => ['camera' => (string) $stop['camera'], 'title' => trim((string) $stop['title'])], $payload['twin_tour_stops'] ?? []);
            $payload['floor_plans'] = array_map(fn (array $plan): array => [
                'label' => trim((string) $plan['label']),
                'media_asset_id' => (int) $plan['media_asset_id'],
                'note' => filled($plan['note'] ?? null) ? trim((string) $plan['note']) : null,
                'is_example' => (bool) ($plan['is_example'] ?? false),
            ], $payload['floor_plans'] ?? []);
        }

        return $this->normalizePhotos($payload);
    }

    /**
     * The rules of the 3D walkthrough and the floor plans (OD-181): a model of this property, guided views at its own
     * camera positions, and floor plans this property may show.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function exploreRules(array $payload): array
    {
        $rules = app(ContentRules::class);

        return [
            'twin_model_id' => ['nullable', 'integer', Rule::exists('twin_models', 'id')->where('property_id', $this->property_id)],
            'twin_tour_stops' => ['nullable', 'array', 'list', 'max:'.self::MAX_TOUR_STOPS, Rule::prohibitedIf(blank($payload['twin_model_id'] ?? null))],
            'twin_tour_stops.*' => ['array'],
            'twin_tour_stops.*.camera' => ['required', 'string', 'distinct', $this->cameraRule($payload['twin_model_id'] ?? null)],
            'twin_tour_stops.*.title' => $rules->plainText(true, 60),
            'floor_plans' => ['nullable', 'array', 'list', 'max:'.self::MAX_FLOOR_PLANS],
            'floor_plans.*' => ['array'],
            'floor_plans.*.label' => $rules->plainText(true, 60),
            'floor_plans.*.media_asset_id' => ['required', 'integer', Rule::exists('media_assets', 'id'), $this->floorPlanRule($payload)],
            'floor_plans.*.note' => $rules->plainText(false, 300),
            'floor_plans.*.is_example' => ['nullable', 'boolean'],
        ];
    }

    /**
     * A guided view stands at one of the chosen model's own camera positions (its SG_CAM_ nodes).
     */
    private function cameraRule(mixed $twinModelId): Closure
    {
        $cameras = is_numeric($twinModelId)
            ? TwinAnchor::query()->where('twin_model_id', (int) $twinModelId)->where('anchor_type', AnchorType::Camera)->pluck('node_name')->all()
            : [];

        return function (string $attribute, mixed $value, Closure $fail) use ($cameras): void {
            if (! in_array($value, $cameras, true)) {
                $fail('Choose one of the model\'s camera positions.');
            }
        };
    }

    /**
     * SmartGro's own or a commissioned plan of this property with a valid licence; a render or concept only as an
     * example, where sample content is allowed (BR-MED-2, OD-181).
     *
     * @param  array<string, mixed>  $payload
     */
    private function floorPlanRule(array $payload): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($payload): void {
            $asset = is_numeric($value) ? MediaAsset::query()->find((int) $value) : null;

            if ($asset === null) {
                return;
            }

            $isExample = (bool) ($payload['floor_plans'][(int) explode('.', $attribute)[1]]['is_example'] ?? false);

            if ($isExample && ! config('backoffice.sample_content')) {
                $fail('Example floor plans are sample content, which is not allowed here. Replace it with SmartGro\'s own or a commissioned plan of this property.');
            } elseif ($isExample && ! in_array($asset->origin, self::EXAMPLE_FLOOR_PLAN_ORIGINS, true)) {
                $fail('An example floor plan must be a render or concept from the media library, so it is never taken for a real plan.');
            } elseif (! $isExample && ! in_array($asset->origin, self::FLOOR_PLAN_ORIGINS, true)) {
                $fail('Use SmartGro\'s own or a commissioned plan of the property. A render or concept can only be an example floor plan.');
            } elseif (! $isExample && (int) $asset->depicts_property_id !== (int) $this->property_id) {
                $fail('Choose a floor plan uploaded for this property.');
            } elseif ($asset->isLicenceExpired()) {
                $fail('This image\'s licence has expired, so it cannot be used.');
            }
        };
    }

    /**
     * A property can be published once its state allows it, its address is complete and operations
     * staff have recorded at least one workspace category it offers (BR-PROP-3; every offering is
     * enquiry-only until inventory exists). The floor-and-space part of BR-PROP-3 applies only once the
     * owner switches it on under the transition plan (config inventory.enforce_floor_rule_on_publication,
     * OD-128 b); like every check here it runs when a version is submitted, scheduled or published,
     * never while a draft is written, and it never takes a published property off the website.
     */
    protected function publicationBlockers(): array
    {
        $property = Property::query()->lockForUpdate()->findOrFail($this->property_id);

        return [
            ...$this->propertyBlockers($property),
            ...($property->activeProducts()->exists() ? [] : ['Operations staff must record at least one workspace category this property offers first.']),
            ...(config('inventory.enforce_floor_rule_on_publication') ? $property->inventoryPublicationGaps() : []),
        ];
    }
}
