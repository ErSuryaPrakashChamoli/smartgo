<?php

namespace App\Actions\Media;

use App\Models\MediaAsset;
use App\Models\User;

/**
 * Audits a media library change (SECURITY_MODEL §7).
 */
class RecordMediaActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(MediaAsset $asset, User $actor, string $event, string $description, array $properties = []): void
    {
        activity('content')
            ->performedOn($asset)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['title' => $asset->title, 'origin' => $asset->origin->value, ...$properties])
            ->log($description);
    }
}
