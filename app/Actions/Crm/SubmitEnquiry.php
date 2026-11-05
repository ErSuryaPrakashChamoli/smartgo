<?php

namespace App\Actions\Crm;

use App\Enums\Crm\LeadStatus;
use App\Models\City;
use App\Models\Lead;
use App\Models\Offering;
use App\Models\Product;
use App\Models\Property;
use App\Services\Crm\VisitorIntake;
use App\Services\Website\EnquiryForm;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Stores a website enquiry (BR-CRM-1, FR-BK-1). It never reserves, books or charges anything.
 *
 * Only what is published can be asked about: the property must be on the website, the workspace
 * category shown, and the offering is linked only when it is published at that property. IDs sent by
 * the browser are re-read, never trusted. The visitor's details attach to an existing contact with the
 * same normalised email or phone without overwriting it (BR-CRM-2). A repeat of the same enquiry within
 * ten minutes returns the first one instead of a duplicate: the contact locks keep such repeats apart,
 * so the checks are plain reads (a locking read would make two new visitors deadlock). The visitor's agreement and the privacy
 * notice version shown are recorded. Personal data is never written to the logs. The visitor's
 * details are taken in as for a tour request (VisitorIntake). Once a new enquiry is stored, the
 * assignment rules give it an owner or leave it unassigned (AssignNewLead, BR-CRM-3); a repeat does not
 * run them again.
 */
class SubmitEnquiry
{
    public const int DUPLICATE_MINUTES = 10;

    public const int MAX_PER_CONTACT_PER_DAY = VisitorIntake::MAX_PER_CONTACT_PER_DAY;

    public function __construct(private EnquiryForm $form, private WorkspaceCatalogue $catalogue, private VisitorIntake $intake, private AssignNewLead $assignNewLead) {}

    /**
     * @param  array<string, mixed>  $input
     * @return Lead|null the enquiry, or null when the submission is treated as spam (nothing stored)
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?string $ipAddress): ?Lead
    {
        $notice = $this->form->privacyNotice();

        if ($notice === null) {
            throw ValidationException::withMessages(['form' => 'Online enquiries open once SmartGro publishes its privacy notice. Please use the contact details on this page.']);
        }

        $validated = Validator::make($this->intake->normalized($input), $this->rules(), $this->messages(), $this->attributes())->validate();

        if ($this->intake->looksAutomated($input)) {
            return null;
        }

        $context = $this->context($validated);
        $this->intake->limitPerContact($validated);

        return $this->intake->whileHoldingContactLocks($validated, fn (): Lead => DB::transaction(function () use ($validated, $context, $notice, $ipAddress): Lead {
            $contact = $this->intake->contact($validated);

            $duplicate = Lead::query()->where('contact_id', $contact->getKey())
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_MINUTES))
                ->where('property_id', $context['property_id'])->where('product_id', $context['product_id'])->where('city_id', $context['city_id'])
                ->latest('id')->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            $lead = (new Lead)->forceFill([
                'reference' => $this->intake->reference('ENQ'),
                'contact_id' => $contact->getKey(),
                'source' => $context['source'],
                'status' => LeadStatus::New,
                'submitted_name' => $validated['name'],
                'submitted_email' => $validated['email'] ?? null,
                'submitted_phone' => $validated['phone'] ?? null,
                'submitted_company' => $validated['company'] ?? null,
                ...$context['ids'],
                'customer_segment_id' => $validated['segment'] ?? null,
                'team_size' => $validated['team_size'] ?? null,
                'message' => $validated['message'] ?? null,
                ...collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])->mapWithKeys(fn (string $key): array => [$key => $validated[$key] ?? null])->all(),
                'landing_path' => $validated['from'] ?? null,
                'consent_snapshot' => $this->intake->consentSnapshot($notice, $ipAddress),
            ]);
            $lead->save();
            DB::afterCommit(fn () => $this->assignNewLead->handleSafely($lead));

            return $lead;
        }));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            ...$this->intake->contactRules(),
            'team_size' => ['nullable', Rule::in(array_keys(EnquiryForm::TEAM_SIZES))],
            'segment' => ['nullable', 'integer', Rule::exists('customer_segments', 'id')->where('is_active', true)],
            'message' => ['nullable', 'string', 'max:2000'],
            'property' => ['nullable', 'integer'],
            'workspace' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'consent' => ['accepted'],
            'from' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s]*$/'],
            ...collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])->mapWithKeys(fn (string $key): array => [$key => $this->intake->plainText(false, 120)])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            ...$this->intake->contactMessages(),
            'from.regex' => 'The form could not be read. Please send it again.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return ['team_size' => 'team size', 'segment' => 'description'];
    }

    /**
     * What the enquiry is about, re-read from published records. Anything not published is refused.
     *
     * @param  array<string, mixed>  $validated
     * @return array{source: string, property_id: int|null, product_id: int|null, city_id: int|null, ids: array<string, int|null>}
     *
     * @throws ValidationException
     */
    private function context(array $validated): array
    {
        $property = isset($validated['property']) ? Property::query()->publiclyListed()->with('city')->find($validated['property']) : null;
        $product = isset($validated['workspace']) ? Product::query()->shown()->where('slug', $validated['workspace'])->first() : null;
        $waitlistCity = isset($validated['city']) ? $this->catalogue->comingSoonCities()->firstWhere('slug', $validated['city']) : null;

        $errors = array_filter([
            'property' => isset($validated['property']) && $property === null ? 'Choose a SmartGro location from the list.' : null,
            'workspace' => isset($validated['workspace']) && $product === null ? 'Choose a workspace type from the list.' : null,
            'city' => isset($validated['city']) && $waitlistCity === null ? 'Choose a city from the list.' : null,
        ]);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $offering = $property !== null && $product !== null
            ? Offering::query()->publiclyListed()->where('property_id', $property->getKey())->where('product_id', $product->getKey())->first()
            : null;
        $cityId = $property?->city_id ?? ($waitlistCity instanceof City ? $waitlistCity->getKey() : null);

        return [
            'source' => $property === null && $waitlistCity !== null ? Lead::SOURCE_CITY_WAITLIST : Lead::SOURCE_WEBSITE_FORM,
            'property_id' => $property?->getKey(),
            'product_id' => $product?->getKey(),
            'city_id' => $cityId,
            'ids' => ['property_id' => $property?->getKey(), 'product_id' => $product?->getKey(), 'offering_id' => $offering?->getKey(), 'city_id' => $cityId],
        ];
    }
}
