<?php

namespace App\Actions\Crm;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Copies details typed on an enquiry to its contact, only those the staff member chose and only where
 * they differ (BR-CRM-2: a later enquiry never changes the contact without staff confirmation). An email
 * or phone number another contact already has is refused, so one visitor does not end up under two
 * records with the same details. The enquiry and the contact are locked; the actor needs permission to
 * work on the enquiry, within its property scope. Audited with the names of the fields changed, never
 * their values.
 */
class UpdateContactFromLead
{
    /**
     * @param  list<string>  $fields  keys of Lead::CONTACT_FIELDS
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Lead $lead, User $actor, array $fields): Contact
    {
        return DB::transaction(function () use ($lead, $actor, $fields): Contact {
            $lead = Lead::query()->with('property')->lockForUpdate()->findOrFail($lead->getKey());
            Gate::forUser($actor)->authorize('update', $lead);

            $contact = Contact::query()->lockForUpdate()->findOrFail($lead->contact_id);
            $differences = $lead->setRelation('contact', $contact)->contactDifferences();
            $fields = array_values(array_unique($fields));

            if ($fields === [] || array_diff($fields, array_keys($differences)) !== []) {
                throw ValidationException::withMessages(['fields' => 'Choose details that differ from the contact. Reload the enquiry if someone else has just updated it.']);
            }

            foreach (['email', 'phone'] as $identifier) {
                $column = Lead::CONTACT_FIELDS[$identifier]['contact'];

                if (in_array($identifier, $fields, true) && Contact::query()->whereKeyNot($contact->getKey())->where($column, $differences[$identifier]['submitted'])->exists()) {
                    throw ValidationException::withMessages(['fields' => 'Another contact already has this '.strtolower(Lead::CONTACT_FIELDS[$identifier]['label']).', so it was not copied.']);
                }
            }

            foreach ($fields as $field) {
                $contact->{Lead::CONTACT_FIELDS[$field]['contact']} = $differences[$field]['submitted'];
            }

            $contact->save();

            activity('leads')
                ->performedOn($lead)
                ->causedBy($actor)
                ->event('contact_updated')
                ->withProperties(['reference' => $lead->reference, 'contact_id' => $contact->getKey(), 'fields' => $fields])
                ->log('Contact details taken from this enquiry: '.collect($fields)->map(fn (string $field): string => strtolower(Lead::CONTACT_FIELDS[$field]['label']))->join(', ', ' and '));

            return $contact;
        });
    }
}
