<?php

namespace App\Actions\Twin;

use App\Models\TwinModel;
use App\Models\User;

/**
 * Audits a 3D model change (SECURITY_MODEL §7).
 */
class RecordTwinModelActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(TwinModel $twinModel, User $actor, string $event, string $description, array $properties = []): void
    {
        activity('content')
            ->performedOn($twinModel)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['title' => $twinModel->title, 'capture_type' => $twinModel->capture_type->value, ...$properties])
            ->log($description);
    }
}
