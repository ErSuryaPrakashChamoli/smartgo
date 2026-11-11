<?php

namespace App\Actions\Crm;

use App\Enums\Crm\PipelineOutcome;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves an open deal to another of the Super Admin's active open stages, forwards or back (FR-CRM-4). A
 * deal is won or lost only through WinDeal and LoseDeal. The deal is locked and a move someone else made
 * since the actor looked is refused rather than overwritten. Audited.
 */
class MoveDeal
{
    /**
     * @param  int  $expectedStageId  the stage the actor saw
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, int $stageId, int $expectedStageId): Deal
    {
        return DB::transaction(function () use ($actor, $deal, $stageId, $expectedStageId): Deal {
            $deal = Deal::query()->with(['lead.property', 'stage'])->lockForUpdate()->findOrFail($deal->getKey());
            Gate::forUser($actor)->authorize('update', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'This deal is '.strtolower($deal->outcome()->label()).', so it stays where it closed.']);
            }

            if ($deal->pipeline_stage_id !== $expectedStageId) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'Someone else moved this deal. Reload it and try again.']);
            }

            $stage = PipelineStage::query()->offered(PipelineOutcome::Open)->whereKey($stageId)->first();

            if ($stage === null || $stage->is($deal->stage)) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'Choose another of the open pipeline stages.']);
            }

            $previous = $deal->stage;
            $deal->forceFill(['pipeline_stage_id' => $stage->getKey()])->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_moved')
                ->withProperties(['deal_id' => $deal->getKey(), 'from' => $previous->name, 'to' => $stage->name])
                ->log('Deal moved from "'.$previous->name.'" to "'.$stage->name.'"');

            return $deal;
        });
    }
}
