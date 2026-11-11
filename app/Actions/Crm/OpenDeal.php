<?php

namespace App\Actions\Crm;

use App\Enums\Crm\PipelineOutcome;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Crm\DealValues;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens a deal from an enquiry (FR-CRM-4) in one of the Super Admin's open stages, with the seats, monthly
 * value and expected close date staff type, each optional; nothing is preset. The opener owns it. The
 * enquiry is locked and the actor's permission and property scope are checked again (BR-CRM-6). A closed
 * enquiry gets no deal, and an enquiry has at most one open deal at a time, so a double submission or two
 * people at once cannot open two. Audited.
 */
class OpenDeal
{
    /**
     * @param  array<string, mixed>  $data  pipeline_stage_id, seats, monthly_value (rupees), expected_close_on
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, array $data): Deal
    {
        if (! Deal::isAvailable()) {
            throw ValidationException::withMessages(['pipeline_stage_id' => 'Deals are not available on this database yet.']);
        }

        $validated = DealValues::validate($data, ['pipeline_stage_id' => ['required', 'integer']]);

        return DB::transaction(function () use ($actor, $lead, $validated): Deal {
            $lead = Lead::query()->with('property')->lockForUpdate()->findOrFail($lead->getKey());
            Gate::forUser($actor)->authorize('update', $lead);

            if ($lead->status->isClosed()) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'This enquiry is '.strtolower($lead->status->label()).'. Reopen it before opening a deal.']);
            }

            $open = Deal::query()->where('lead_id', $lead->getKey())->whereNull('won_at')->whereNull('lost_at')->first();

            if ($open !== null) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'This enquiry already has an open deal (deal '.$open->getKey().'). Work on that one, or close it first.']);
            }

            $stage = $this->openStage((int) $validated['pipeline_stage_id']);

            $deal = (new Deal)->forceFill([
                'lead_id' => $lead->getKey(),
                'pipeline_stage_id' => $stage->getKey(),
                'owner_user_id' => $actor->getKey(),
                ...DealValues::columns($validated),
                'created_by' => $actor->getKey(),
            ]);
            $deal->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_opened')
                ->withProperties(['deal_id' => $deal->getKey(), 'reference' => $lead->reference, 'stage' => $stage->name])
                ->log('Deal opened in "'.$stage->name.'"');

            return $deal;
        });
    }

    /**
     * The chosen stage, if it is an active open one.
     *
     * @throws ValidationException
     */
    private function openStage(int $stageId): PipelineStage
    {
        if (! PipelineStage::query()->offered(PipelineOutcome::Open)->exists()) {
            throw ValidationException::withMessages(['pipeline_stage_id' => 'No open pipeline stages are set up yet, so no deal can be opened. The Super Admin adds them in Sales → Pipeline stages.']);
        }

        return PipelineStage::query()->offered(PipelineOutcome::Open)->whereKey($stageId)->first()
            ?? throw ValidationException::withMessages(['pipeline_stage_id' => 'Choose one of the open pipeline stages.']);
    }
}
