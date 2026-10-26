<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Enums\Content\RevisionState;
use App\Models\Contracts\Revisionable;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may move a revision through the workflow (BR-CMS-1, BR-CMS-2, BR-CMS-2a; SECURITY_MODEL §11.6).
 * Everyone who edited a version is its author and may not approve it; the approver may not publish,
 * schedule or restore that version. The Super Admin follows the same rules: there is no bypass.
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
            Capability::ViewContent, Capability::ManageContactSettings, Capability::ManageBrand,
            Capability::ManageTracking, Capability::ViewLegalSettings, Capability::ManageRedirects,
        ])->contains(fn (Capability $capability): bool => $user->can($capability));
    }

    /**
     * Reading a version and its preview.
     */
    public function view(User $user, Revision $revision): Response
    {
        return $user->can($this->capabilities($revision)['view'])
            ? Response::allow()
            : Response::deny('You cannot view this content.');
    }

    public function create(User $user, Revisionable $content): Response
    {
        return $user->can($content->revisionCapabilities()['edit'])
            ? Response::allow()
            : Response::deny('You cannot edit this content.');
    }

    public function update(User $user, Revision $revision): Response
    {
        if (! $user->can($this->capabilities($revision)['edit'])) {
            return Response::deny('You cannot edit this content.');
        }

        return $revision->state === RevisionState::Draft
            ? Response::allow()
            : Response::deny('Only a draft can be changed. Start a new draft to change this version.');
    }

    public function submit(User $user, Revision $revision): Response
    {
        if (! $user->can($this->capabilities($revision)['edit'])) {
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
        if (! $user->can($this->capabilities($revision)['approve'])) {
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
        if (! $user->can($this->capabilities($revision)['publish'])) {
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
     * Cancelling a scheduled publication leaves the version approved and unpublished.
     */
    public function unschedule(User $user, Revision $revision): Response
    {
        if (! $user->can($this->capabilities($revision)['publish'])) {
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
        if (! $user->can($this->capabilities($revision)['edit'])) {
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
     * @return array{view: Capability, edit: Capability, approve: Capability, publish: Capability}
     */
    private function capabilities(Revision $revision): array
    {
        /** @var Revisionable $content */
        $content = $revision->revisionable;

        return $content->revisionCapabilities();
    }
}
