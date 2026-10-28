<?php

namespace App\Actions\Twin;

use App\Models\TwinModel;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a 3D model, its anchors and its file. No scene exists yet to use a model; once scenes are
 * built (FR-3D-2), a model a scene uses must be kept.
 */
class DeleteTwinModel
{
    public function __construct(private RecordTwinModelActivity $recordTwinModelActivity) {}

    public function handle(TwinModel $twinModel, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $twinModel);

        $this->recordTwinModelActivity->handle($twinModel, $actor, 'twin_model_deleted', '3D model deleted', [
            'property' => $twinModel->property?->code,
        ]);

        $twinModel->delete();
    }
}
