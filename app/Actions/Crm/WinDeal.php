<?php

namespace App\Actions\Crm;

use App\Actions\Inventory\AllocateWonDeal;
use App\Enums\Crm\PipelineOutcome;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\PipelineStage;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Marks an open deal won, in one of the Super Admin's active won stages (FR-CRM-4). When spaces are
 * proposed, the agreement's reference and dates are required and every proposed space is allocated
 * through the inventory (AllocateWonDeal, BR-CRM-7), all in one transaction: if any space is no longer
 * free, nothing is allocated and the deal stays open. The deal, its proposals and their spaces are locked
 * first, before anything is read, so a deal is won once, however many times or by however many people it
 * is submitted. The enquiry's status is not changed (no rule links them yet, A-42). Audited.
 */
class WinDeal
{
    public function __construct(private AllocateWonDeal $allocateWonDeal) {}

    /**
     * @param  array<string, mixed>  $data  pipeline_stage_id, agreement_reference, starts_on, ends_on
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, array $data): Deal
    {
        Validator::make($data, ['pipeline_stage_id' => ['required', 'integer']], [], ['pipeline_stage_id' => 'stage'])->validate();

        return DB::transaction(function () use ($actor, $deal, $data): Deal {
            // Locking reads only, before any plain read (see AllocateWonDeal): the deal, its proposals and their
            // spaces in ascending id order, so the availability check sees every allocation already committed.
            $deal = Deal::query()->lockForUpdate()->findOrFail($deal->getKey());
            $spaceIds = DealSpace::query()->where('deal_id', $deal->getKey())->lockForUpdate()->pluck('space_id')->unique()->all();
            Space::query()->whereKey($spaceIds)->orderBy('id')->lockForUpdate()->pluck('id');

            $deal->load('lead.property');
            Gate::forUser($actor)->authorize('update', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['pipeline_stage_id' => 'This deal is already '.strtolower($deal->outcome()->label()).'.']);
            }

            $stage = PipelineStage::query()->offered(PipelineOutcome::Won)->whereKey((int) $data['pipeline_stage_id'])->first()
                ?? throw ValidationException::withMessages(['pipeline_stage_id' => PipelineStage::query()->offered(PipelineOutcome::Won)->exists()
                    ? 'Choose one of the won stages.'
                    : 'No won stage is set up yet, so no deal can be marked won. The Super Admin adds one in Sales → Pipeline stages.']);

            $allocations = 0;
            $agreement = null;

            if ($deal->proposedSpaces()->exists()) {
                $agreement = $this->agreement($data);
                $allocations = $this->allocateWonDeal->handle($actor, $deal, $agreement['reference'], $agreement['from'], $agreement['until']);
            }

            $deal->forceFill([
                'pipeline_stage_id' => $stage->getKey(),
                'won_at' => now(),
                'agreement_reference' => $agreement['reference'] ?? null,
            ])->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_won')
                ->withProperties(['deal_id' => $deal->getKey(), 'stage' => $stage->name, 'agreement_reference' => $agreement['reference'] ?? null, 'allocations' => $allocations])
                ->log('Deal won'.($allocations > 0 ? ': '.$allocations.' '.str('allocation')->plural($allocations).' recorded' : ''));

            return $deal;
        });
    }

    /**
     * The agreement the allocations carry, checked as an allocation's would be (FR-INV-3).
     *
     * @param  array<string, mixed>  $data
     * @return array{reference: string, from: CarbonImmutable, until: CarbonImmutable|null}
     *
     * @throws ValidationException
     */
    private function agreement(array $data): array
    {
        $validated = Validator::make($data, [
            'agreement_reference' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/.-]*$/'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'after_or_equal:'.BusinessDay::today()->toDateString()],
        ], [
            'agreement_reference.regex' => 'Use letters, numbers, hyphens, dots and slashes, for example AG-2027-014.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date or in the past.',
        ], ['starts_on' => 'start date', 'ends_on' => 'end date'])->validate();

        return [
            'reference' => $validated['agreement_reference'],
            'from' => CarbonImmutable::parse($validated['starts_on'])->startOfDay(),
            'until' => filled($validated['ends_on'] ?? null) ? CarbonImmutable::parse($validated['ends_on'])->startOfDay() : null,
        ];
    }
}
