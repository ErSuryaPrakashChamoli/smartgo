<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use App\Services\Content\SiteSettingPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A group of public settings (FR-CMS-8, BR-CMS-9). The row holds only the published values; changes
 * are revisions approved and published by different people, with the permissions of the group
 * (OD-64, OD-67, OD-71; legal details are verified, OD-55). The groups are fixed.
 */
class SiteSetting extends Model implements Revisionable
{
    use HasRevisions;

    /**
     * @var array<string, array{name: string, purpose: string}>
     */
    public const array GROUPS = [
        'contact' => ['name' => 'Contact details', 'purpose' => 'Phone, email, WhatsApp, address and opening hours, shown in the website footer.'],
        'social' => ['name' => 'Social links', 'purpose' => 'Links to SmartGro profiles on social networks, shown in the website footer (brand settings).'],
        'tracking' => ['name' => 'Tracking IDs', 'purpose' => 'Analytics and advertising identifiers. Not added to the website until cookie consent is decided (OD-100).'],
        'legal' => ['name' => 'Legal entity details', 'purpose' => 'Legal name, registered address, GSTIN and CIN for invoices and legal pages, once verified. Not shown on the website.'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
            'published_at' => 'datetime',
        ];
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
     * @return array{name: string, purpose: string}
     */
    public function group(): array
    {
        return self::GROUPS[$this->handle];
    }

    /**
     * The published value of a field, or null.
     */
    public function value(string $field): ?string
    {
        return $this->values[$field] ?? null;
    }

    /**
     * The groups the user may open.
     *
     * @return list<string>
     */
    public static function handlesVisibleTo(User $user): array
    {
        return array_values(array_filter(array_keys(self::GROUPS), fn (string $handle): bool => $user->can((new self)->forceFill(['handle' => $handle])->revisionCapabilities()['view'])));
    }

    public function revisionCapabilities(): array
    {
        return match ($this->handle) {
            'contact' => ['view' => Capability::ManageContactSettings, 'edit' => Capability::ManageContactSettings, 'approve' => Capability::ApproveContactSettings, 'publish' => Capability::PublishContactSettings],
            'social' => ['view' => Capability::ManageBrand, 'edit' => Capability::ManageBrand, 'approve' => Capability::ApproveBrand, 'publish' => Capability::PublishBrand],
            'tracking' => ['view' => Capability::ManageTracking, 'edit' => Capability::ManageTracking, 'approve' => Capability::ApproveTracking, 'publish' => Capability::PublishTracking],
            'legal' => ['view' => Capability::ViewLegalSettings, 'edit' => Capability::ManageLegalSettings, 'approve' => Capability::VerifyLegalSettings, 'publish' => Capability::ManageLegalSettings],
        };
    }

    public function toRevisionPayload(): array
    {
        return [...app(SiteSettingPayload::class)->blank($this->handle), ...($this->values ?? [])];
    }

    public function prepareRevisionPayload(array $payload, bool $forSubmission): array
    {
        return app(SiteSettingPayload::class)->validate($this, $payload, $forSubmission);
    }

    public function applyPublishedRevision(Revision $revision): void
    {
        $this->forceFill([
            'values' => $revision->payload,
            'published_revision_id' => $revision->getKey(),
            'published_at' => $revision->published_at,
        ])->save();
    }
}
