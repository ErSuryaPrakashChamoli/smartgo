<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasWebsiteProfile;
use App\Models\Contracts\BelongsToProperty;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentRules;
use Database\Factories\PropertyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 */
class PropertyProfile extends Model implements BelongsToProperty, Unpublishable
{
    /** @use HasFactory<PropertyProfileFactory> */
    use HasFactory, HasRevisions, HasWebsiteProfile;

    /**
     * The "Getting here" details, in the order the property page shows them.
     *
     * @var list<string>
     */
    public const array GETTING_HERE_FIELDS = ['nearest_metro', 'metro_walk_minutes', 'buses', 'parking', 'accessibility'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['gallery_asset_ids' => 'array', 'published_at' => 'datetime'];
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
        return ['headline', 'summary', 'getting_there', ...self::GETTING_HERE_FIELDS, 'phone', 'whatsapp', 'email', 'hero_asset_id', 'gallery_asset_ids'];
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
            ],
            'messages' => [...$rules->messages(), ...$this->photoAndSeoMessages(), 'metro_walk_minutes.prohibited' => 'Add the nearest metro station first.'],
            'attributes' => ['getting_there' => 'getting there', 'nearest_metro' => 'nearest metro station', 'metro_walk_minutes' => 'walk from the metro', 'whatsapp' => 'WhatsApp number', ...$this->photoAndSeoAttributes()],
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        $payload['metro_walk_minutes'] = $payload['metro_walk_minutes'] === null ? null : (int) $payload['metro_walk_minutes'];

        return $this->normalizePhotos($payload);
    }

    /**
     * A property can be published once its state allows it, its address is complete and operations
     * staff have recorded at least one workspace category it offers (BR-PROP-3; every offering is
     * enquiry-only until inventory exists). Floors are not checked: they are not built yet.
     */
    protected function publicationBlockers(): array
    {
        $property = Property::query()->lockForUpdate()->findOrFail($this->property_id);

        return [
            ...$this->propertyBlockers($property),
            ...($property->activeProducts()->exists() ? [] : ['Operations staff must record at least one workspace category this property offers first.']),
        ];
    }
}
