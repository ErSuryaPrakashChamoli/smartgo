<?php

namespace App\Actions\Crm;

use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Takes a proposed space off an open deal (FR-CRM-4). Nothing was reserved for it, so nothing is released.
 * A won or lost deal keeps its proposed spaces on record. The deal is locked and the actor's permission and
 * scope are checked again. Audited.
 */
class WithdrawDealSpace
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, DealSpace $line): Deal
    {
        return DB::transaction(function () use ($actor, $line): Deal {
            $deal = Deal::query()->with('lead.property')->lockForUpdate()->findOrFail($line->deal_id);
            Gate::forUser($actor)->authorize('update', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['space_id' => 'This deal is '.strtolower($deal->outcome()->label()).', so its proposed spaces stay on record.']);
            }

            $line = DealSpace::query()->with(['space', 'seat'])->where('deal_id', $deal->getKey())->find($line->getKey())
                ?? throw ValidationException::withMessages(['space_id' => 'This space is no longer proposed on the deal.']);
            $description = $line->space->displayName().', '.lcfirst($line->portion());
            $line->delete();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_space_withdrawn')
                ->withProperties(['deal_id' => $deal->getKey(), 'space' => $line->space->code, 'seat' => $line->seat?->label, 'seats' => $line->seats])
                ->log('Proposed space withdrawn: '.$description);

            return $deal;
        });
    }
}
