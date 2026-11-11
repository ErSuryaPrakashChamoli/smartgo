<?php

namespace App\Actions\Twin;

use App\Models\PropertyProfile;
use App\Models\Revision;
use App\Models\TwinModel;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a 3D model, its anchors and its file, but never one that a location's website profile uses, live or in
 * any version (OD-181), so history and restores keep working; a corrected file is uploaded as a new model.
 */
class DeleteTwinModel
{
    public function __construct(private RecordTwinModelActivity $recordTwinModelActivity) {}

    /**
     * @throws ValidationException when a profile or a profile version uses the model
     */
    public function handle(TwinModel $twinModel, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $twinModel);

        if ($this->isUsed($twinModel)) {
            throw ValidationException::withMessages(['twin_model' => 'A location\'s website profile, or a version of it, uses this 3D model, so it cannot be deleted. Choose another model there first.']);
        }

        $this->recordTwinModelActivity->handle($twinModel, $actor, 'twin_model_deleted', '3D model deleted', [
            'property' => $twinModel->property?->code,
        ]);

        $twinModel->delete();
    }

    private function isUsed(TwinModel $twinModel): bool
    {
        return PropertyProfile::hasExplore()
            && (PropertyProfile::query()->where('twin_model_id', $twinModel->getKey())->exists()
                || Revision::query()->where('revisionable_type', (new PropertyProfile)->getMorphClass())
                    ->whereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['twin_model_id' => $twinModel->getKey()])])
                    ->exists());
    }
}
