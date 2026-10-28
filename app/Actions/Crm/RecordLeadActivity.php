<?php

namespace App\Actions\Crm;

use App\Enums\Crm\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records an internal note, a contact made (call, email, WhatsApp, meeting) or a follow-up with its
 * due date on an enquiry. Recording a contact counts as the first response. Notes are staff-only and
 * never edited or deleted; the audit log records that one was added, not its text.
 */
class RecordLeadActivity
{
    public const int MAX_BODY_LENGTH = 2000;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Lead $lead, User $actor, LeadActivityType $type, string $body, ?CarbonInterface $dueAt = null): LeadActivity
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw ValidationException::withMessages(['body' => 'Write the note, up to 2,000 characters.']);
        }

        if ($type === LeadActivityType::FollowUp && ($dueAt === null || $dueAt->isPast())) {
            throw ValidationException::withMessages(['due_at' => 'Choose when the follow-up is due, in the future.']);
        }

        return DB::transaction(function () use ($lead, $actor, $type, $body, $dueAt): LeadActivity {
            $lead = Lead::query()->with('property')->lockForUpdate()->findOrFail($lead->getKey());
            Gate::forUser($actor)->authorize('update', $lead);

            $activity = (new LeadActivity)->forceFill([
                'lead_id' => $lead->getKey(),
                'user_id' => $actor->getKey(),
                'type' => $type,
                'body' => $body,
                'due_at' => $type === LeadActivityType::FollowUp ? $dueAt : null,
            ]);
            $activity->save();

            if ($type->isContact() && $lead->first_responded_at === null) {
                $lead->forceFill(['first_responded_at' => now()])->save();
            }

            activity('leads')
                ->performedOn($lead)
                ->causedBy($actor)
                ->event('lead_activity_recorded')
                ->withProperties(['reference' => $lead->reference, 'type' => $type->value, 'due_at' => $activity->due_at?->toIso8601String()])
                ->log($type->label().' recorded');

            return $activity;
        });
    }

    /**
     * Marks an open follow-up as done, once.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function complete(LeadActivity $followUp, User $actor): LeadActivity
    {
        return DB::transaction(function () use ($followUp, $actor): LeadActivity {
            $lead = Lead::query()->with('property')->lockForUpdate()->findOrFail($followUp->lead_id);
            Gate::forUser($actor)->authorize('update', $lead);
            $followUp = LeadActivity::query()->lockForUpdate()->findOrFail($followUp->getKey());

            if (! $followUp->isOpenFollowUp()) {
                throw ValidationException::withMessages(['follow_up' => 'This follow-up is already done.']);
            }

            $followUp->forceFill(['completed_at' => now(), 'completed_by' => $actor->getKey()])->save();

            activity('leads')
                ->performedOn($lead)
                ->causedBy($actor)
                ->event('lead_follow_up_completed')
                ->withProperties(['reference' => $lead->reference, 'activity_id' => $followUp->getKey()])
                ->log('Follow-up done');

            return $followUp;
        });
    }
}
