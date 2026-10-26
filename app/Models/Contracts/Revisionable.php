<?php

namespace App\Models\Contracts;

use App\Enums\Access\Capability;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Content that changes only through reviewed revisions (ADR-0005, BR-CMS-1): drafts, approval by
 * someone other than the author, and publication by someone other than the approver (OD-65, OD-73).
 */
interface Revisionable
{
    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany;

    /**
     * The permissions that edit, approve and publish this kind of content (SECURITY_MODEL §11).
     *
     * @return array{edit: Capability, approve: Capability, publish: Capability}
     */
    public function revisionCapabilities(): array;

    /**
     * The live content as a revision payload, used to start a new draft.
     *
     * @return array<string, mixed>
     */
    public function toRevisionPayload(): array;

    /**
     * Copies the revision's payload into the live record and saves it.
     */
    public function applyPublishedRevision(Revision $revision): void;
}
