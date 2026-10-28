<?php

namespace App\Actions\Crm;

use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use App\Models\LeadCloseReason;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves an enquiry to another status along the allowed transitions (LeadStatus::nextStatuses). Lost
 * and disqualified need a reason from the managed list for that status, with optional details
 * (BR-CRM-5); reopening clears them. Leaving "new" records the first response. The enquiry is locked
 * and a status changed by someone else since the actor looked is refused. No status reserves, books or
 * charges anything. Audited.
 */
class ChangeLeadStatus
{
    public const int MAX_DETAILS_LENGTH = 500;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Lead $lead, User $actor, LeadStatus $to, LeadStatus $expected, ?int $reasonId = null, ?string $details = null): Lead
    {
        $details = trim((string) $details);

        return DB::transaction(function () use ($lead, $actor, $to, $expected, $reasonId, $details): Lead {
            $lead = Lead::query()->with('property')->lockForUpdate()->findOrFail($lead->getKey());
            Gate::forUser($actor)->authorize('update', $lead);

            if ($lead->status !== $expected) {
                throw ValidationException::withMessages(['status' => 'Someone else changed this enquiry\'s status. Reload it and try again.']);
            }

            if (! in_array($to, $lead->status->nextStatuses(), true)) {
                throw ValidationException::withMessages(['status' => 'An enquiry that is '.strtolower($lead->status->label()).' cannot be marked '.strtolower($to->label()).'.']);
            }

            $reason = $to->needsReason() ? $this->reason($to, $reasonId) : null;

            if (mb_strlen($details) > self::MAX_DETAILS_LENGTH) {
                throw ValidationException::withMessages(['details' => 'Keep the details under 500 characters.']);
            }

            $previous = $lead->status;
            $lead->forceFill([
                'status' => $to,
                'close_reason_id' => $reason?->getKey(),
                'closed_reason' => $reason !== null && $details !== '' ? $details : null,
                'first_responded_at' => $lead->first_responded_at ?? now(),
            ])->save();

            activity('leads')
                ->performedOn($lead)
                ->causedBy($actor)
                ->event('lead_status_changed')
                ->withProperties(['reference' => $lead->reference, 'from' => $previous->value, 'to' => $to->value, 'reason' => $reason?->label, 'details' => $reason !== null && $details !== '' ? $details : null])
                ->log('Enquiry marked '.strtolower($to->label()));

            return $lead;
        });
    }

    /**
     * The chosen reason, if it is an active one for this status (BR-CRM-5).
     *
     * @throws ValidationException
     */
    private function reason(LeadStatus $status, ?int $reasonId): LeadCloseReason
    {
        if (! LeadCloseReason::query()->offeredFor($status)->exists()) {
            throw ValidationException::withMessages(['close_reason_id' => 'No reasons for marking an enquiry '.strtolower($status->label()).' are set up yet. The Super Admin adds them in Sales → Closing reasons.']);
        }

        $reason = $reasonId === null ? null : LeadCloseReason::query()->offeredFor($status)->whereKey($reasonId)->first();

        if ($reason === null) {
            throw ValidationException::withMessages(['close_reason_id' => 'Choose why the enquiry is '.strtolower($status->label()).' from the list.']);
        }

        return $reason;
    }
}
