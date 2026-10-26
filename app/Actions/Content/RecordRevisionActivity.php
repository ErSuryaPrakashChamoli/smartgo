<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;

/**
 * Writes a workflow step to the audit log with the author, editors, approver and publisher recorded
 * separately (SECURITY_MODEL §7, OD-65).
 */
class RecordRevisionActivity
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function handle(Revision $revision, User $actor, string $event, string $description, array $extra = []): void
    {
        activity('content')
            ->performedOn($revision)
            ->causedBy($actor)
            ->event($event)
            ->withProperties([
                'content' => class_basename($revision->revisionable_type).' #'.$revision->revisionable_id,
                'revision' => $revision->number,
                'author_id' => $revision->author_id,
                'editor_ids' => $revision->editor_ids,
                'reviewer_id' => $revision->reviewer_id,
                'published_by' => $revision->published_by,
                ...$extra,
            ])
            ->log($description);
    }
}
