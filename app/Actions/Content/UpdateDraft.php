<?php

namespace App\Actions\Content;

use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Changes a draft. Anyone who changes it counts as one of its authors and may not approve it (SECURITY_MODEL §11.6).
 */
class UpdateDraft
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Revision $revision, User $editor, array $payload): Revision
    {
        Gate::forUser($editor)->authorize('update', $revision);

        $editorIds = $revision->editor_ids ?? [];

        if (! $revision->wasEditedBy($editor)) {
            $editorIds[] = $editor->getKey();
        }

        $revision->forceFill(['payload' => $payload, 'editor_ids' => $editorIds])->save();

        return $revision;
    }
}
