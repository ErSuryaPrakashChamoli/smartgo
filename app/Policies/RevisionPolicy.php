<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Enums\Content\RevisionState;
use App\Models\Contracts\BelongsToProperty;
use App\Models\Contracts\Revisionable;
use App\Models\Contracts\Unpublishable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may move a revision through the workflow (BR-CMS-1, BR-CMS-2, BR-CMS-2a; SECURITY_MODEL §11.6).
 * Everyone who edited a version is its author and may not approve it; the approver may not publish,
 * schedule or restore that version. The Super Admin follows the same rules: there is no bypass. Content
 * that belongs to a property is open to a Property Manager only for their assigned properties.
 */
class RevisionPolicy
{
    /**
     * A deactivated account can do nothing, including publishing a version it scheduled earlier.
     */
    public function before(User $user, string $ability): ?Response
    {
        return $user->is_active ? null : Response::deny('This account is deactivated.');
    }

    /**
     * Listing versions, which only happens inside an editor the user may already open.
     */
    public function viewAny(User $user): bool
    {
        return collect([
            Capability::ViewContent, Capability::ViewProfiles, Capability::ManageContactSettings, Capability::ManageBrand,
            Capability::ManageTracking, Capability::ViewLegalSettings, Capability::ManageRedirects,
        ])->contains(fn (Capability $capability): bool => $user->can($capability));
    }

    /**
     * Reading a version and its preview.
     */
    public function view(User $user, Revision $revision): Response
    {
        return $this->grants($user, $revision->revisionable, 'view')
            ? Response::allow()
            : Response::deny('You cannot view this content.');
    }

    public function create(User $user, Revisionable $content): Response
    {
        return $this->grants($user, $content, 'edit')
            ? Response::allow()
            : Response::deny('You cannot edit this content.');
    }

    public function update(User $user, Revision $revision): Response
    {
        if (! $this->grants($user, $revision->revisionable, 'edit')) {
            return Response::deny('You cannot edit this content.');
        }

        return $revision->state === RevisionState::Draft
            ? Response::allow()
            : Response::deny('Only a draft can be changed. Start a new draft to change this version.');
    }

    public function submit(User $user, Revision $revision): Response
    {
        if (! $this->grants($user, $revision->revisionable, 'edit')) {
            return Response::deny('You cannot edit this content.');
        }

        if ($revision->state !== RevisionState::Draft) {
            return Response::deny('Only a draft can be submitted for approval.');
        }

        return $revision->wasEditedBy($user)
            ? Response::allow()
            : Response::deny('Only someone who worked on this draft can submit it.');
    }

    public function approve(User $user, Revision $revision): Response
    {
        if (! $this->grants($user, $revision->revisionable, 'approve')) {
            return Response::deny('You cannot approve this content.');
        }

        if ($revision->state !== RevisionState::Submitted) {
            return Response::deny('Only a submitted version can be approved or rejected.');
        }

        return $revision->wasEditedBy($user)
            ? Response::deny('You worked on this version, so someone else must review it.')
            : Response::allow();
    }

    public function reject(User $user, Revision $revision): Response
    {
        return $this->approve($user, $revision);
    }

    /**
     * Publishing now or scheduling: both put the version live (BR-CMS-5).
     */
    public function publish(User $user, Revision $revision): Response
    {
        if (! $this->grants($user, $revision->revisionable, 'publish')) {
            return Response::deny('You cannot publish this content.');
        }

        if ($revision->state !== RevisionState::Approved) {
            return Response::deny('Only an approved version can be published.');
        }

        return $revision->reviewer_id === $user->getKey()
            ? Response::deny('You approved this version, so someone else must publish it.')
            : Response::allow();
    }

    /**
     * Taking live content off the website at once (OD-98): anyone who may publish that content may,
     * including its approver, because removing content never puts anything unapproved live. Only
     * testimonials and customer stories offer it, and only their live version.
     */
    public function unpublish(User $user, Revision $revision): Response
    {
        $content = $revision->revisionable;

        if (! $content instanceof Unpublishable) {
            return Response::deny('This content cannot be taken down here.');
        }

        if (! $this->grants($user, $content, 'publish')) {
            return Response::deny('You cannot take this content down.');
        }

        return $revision->state === RevisionState::Published && $content->getAttribute('status') === 'published' && (int) $content->getAttribute('published_revision_id') === (int) $revision->getKey()
            ? Response::allow()
            : Response::deny('Only live content can be taken down.');
    }

    /**
     * Cancelling a scheduled publication leaves the version approved and unpublished.
     */
    public function unschedule(User $user, Revision $revision): Response
    {
        if (! $this->grants($user, $revision->revisionable, 'publish')) {
            return Response::deny('You cannot publish this content.');
        }

        return $revision->state === RevisionState::Approved && $revision->scheduled_for !== null
            ? Response::allow()
            : Response::deny('Only a scheduled, approved version can be unscheduled.');
    }

    /**
     * Restoring copies an earlier published version into a new draft, which needs approval and
     * publication like any other version (OD-88; replaces republishing it directly).
     */
    public function restore(User $user, Revision $revision): Response
    {
        if (! $this->grants($user, $revision->revisionable, 'edit')) {
            return Response::deny('You cannot edit this content.');
        }

        if ($revision->state !== RevisionState::Superseded) {
            return Response::deny('Only a previously published version can be restored.');
        }

        return $revision->reviewer_id === $user->getKey()
            ? Response::deny('You approved this version, so someone else must restore it.')
            : Response::allow();
    }

    /**
     * Whether the user holds the content's capability for a step, for the content's property when it
     * belongs to one.
     *
     * @param  'view'|'edit'|'approve'|'publish'  $step
     */
    private function grants(User $user, Revisionable $content, string $step): bool
    {
        $capability = $content->revisionCapabilities()[$step];

        return $content instanceof BelongsToProperty
            ? $user->hasCapabilityForProperty($capability, $content->owningProperty())
            : $user->can($capability);
    }
}
