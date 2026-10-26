<?php

namespace App\Models\Contracts;

use App\Enums\Access\Capability;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

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
     * The permissions that view, edit, approve and publish this kind of content (SECURITY_MODEL §11).
     *
     * @return array{view: Capability, edit: Capability, approve: Capability, publish: Capability}
     */
    public function revisionCapabilities(): array;

    /**
     * Validates a payload on the server and returns it as it is stored. A draft may be incomplete but
     * never unsafe; a version submitted, scheduled or published must be complete.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function prepareRevisionPayload(array $payload, bool $forSubmission): array;

    /**
     * The live content as a revision payload, used to start a new draft.
     *
     * @return array<string, mixed>
     */
    public function toRevisionPayload(): array;

    /**
     * Copies the revision's payload into the live record and saves it, inside the publishing transaction.
     */
    public function applyPublishedRevision(Revision $revision): void;
}
