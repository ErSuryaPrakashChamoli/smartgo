<?php

namespace App\Actions\Crm;

use App\Enums\Crm\PipelineOutcome;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Marks an open deal lost, in one of the Super Admin's active lost stages, with the reason staff give
 * (DATABASE_ERD §6 `lost_reason`). Nothing is allocated; the proposed spaces stay on record. A deal is
 * lost once: the deal is locked, and a deal already won or lost is refused. Audited with the reason.
 */
class LoseDeal
{
    /**
     * @param  array<string, mixed>  $data  pipeline_stage_id, reason
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, array $data): Deal
    {
        $validated = Validator::make($data, [
            'pipeline_stage_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:300', 'not_regex:/[<>]/'],
        ], ['reason.not_regex' => 'Use plain text: < and > are not allowed.'], ['pipeline_stage_id' => 'stage'])->validate();

        return DB::transaction(function () use ($actor, $deal, $validated): Deal {
            $deal = Deal::query()->with('lead.property')->lockForUpdate()->findOrFail($deal->getKey());
            Gate::forUser($actor)->authorize('update', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'This deal is already '.strtolower($deal->outcome()->label()).'.']);
            }

            $stage = PipelineStage::query()->offered(PipelineOutcome::Lost)->whereKey((int) $validated['pipeline_stage_id'])->first()
                ?? throw ValidationException::withMessages(['pipeline_stage_id' => PipelineStage::query()->offered(PipelineOutcome::Lost)->exists()
                    ? 'Choose one of the lost stages.'
                    : 'No lost stage is set up yet, so no deal can be marked lost. The Super Admin adds one in Sales → Pipeline stages.']);

            $deal->forceFill([
                'pipeline_stage_id' => $stage->getKey(),
                'lost_at' => now(),
                'lost_reason' => trim((string) $validated['reason']),
            ])->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_lost')
                ->withProperties(['deal_id' => $deal->getKey(), 'stage' => $stage->name, 'reason' => $deal->lost_reason])
                ->log('Deal lost');

            return $deal;
        });
    }
}
