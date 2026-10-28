<?php

namespace App\Actions\Crm;

use App\Enums\Crm\LeadStatus;
use App\Models\City;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Offering;
use App\Models\Product;
use App\Models\Property;
use App\Services\Website\EnquiryForm;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Stores a website enquiry (BR-CRM-1, FR-BK-1). It never reserves, books or charges anything.
 *
 * Only what is published can be asked about: the property must be on the website, the workspace
 * category shown, and the offering is linked only when it is published at that property. IDs sent by
 * the browser are re-read, never trusted. The visitor's details attach to an existing contact with the
 * same normalised email or phone without overwriting it (BR-CRM-2). A repeat of the same enquiry within
 * ten minutes returns the first one instead of a duplicate. The visitor's agreement and the privacy
 * notice version shown are recorded. Personal data is never written to the logs.
 */
class SubmitEnquiry
{
    public const int DUPLICATE_MINUTES = 10;

    public const int MAX_PER_CONTACT_PER_DAY = 5;

    public function __construct(private EnquiryForm $form, private WorkspaceCatalogue $catalogue) {}

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

        $validated = Validator::make($this->normalized($input), $this->rules(), $this->messages(), $this->attributes())->validate();

        if ($this->looksAutomated($input)) {
            return null;
        }

        $context = $this->context($validated);
        $this->limitPerContact($validated);

        return $this->whileHoldingContactLocks($validated, fn (): Lead => DB::transaction(function () use ($validated, $context, $notice, $ipAddress): Lead {
            $contact = $this->contact($validated);

            $duplicate = Lead::query()->where('contact_id', $contact->getKey())
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_MINUTES))
                ->where('property_id', $context['property_id'])->where('product_id', $context['product_id'])->where('city_id', $context['city_id'])
                ->lockForUpdate()->latest('id')->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            $lead = (new Lead)->forceFill([
                'reference' => $this->reference(),
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
                'consent_snapshot' => [
                    'purpose' => 'service_communication',
                    'text' => EnquiryForm::CONSENT_TEXT,
                    'agreed_at' => now()->toIso8601String(),
                    'notice_path' => '/'.$notice->slug,
                    'notice_page_id' => $notice->getKey(),
                    'notice_revision' => $notice->publishedRevision?->number,
                    'notice_published_at' => $notice->published_at?->toIso8601String(),
                    'marketing' => false,
                    'ip_hash' => $ipAddress === null ? null : hash_hmac('sha256', $ipAddress, (string) config('app.key')),
                ],
            ]);
            $lead->save();

            return $lead;
        }));
    }

    /**
     * Runs the step while holding a database lock per email and phone number, so two identical
     * submissions at the same instant (a double click) cannot both miss each other's contact and
     * enquiry. Locks are taken in a fixed order and always released.
     *
     * @template TResult
     *
     * @param  array<string, mixed>  $validated
     * @param  callable(): TResult  $step
     * @return TResult
     *
     * @throws ValidationException when a lock cannot be had in time
     */
    private function whileHoldingContactLocks(array $validated, callable $step): mixed
    {
        $names = collect([$validated['email'] ?? null, $validated['phone'] ?? null])->filter()
            ->map(fn (string $identifier): string => 'smartgro.enquiry.'.substr(hash_hmac('sha256', $identifier, (string) config('app.key')), 0, 40))
            ->sort()->values();
        $held = [];

        try {
            foreach ($names as $name) {
                if ((int) DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$name])->acquired !== 1) {
                    throw ValidationException::withMessages(['form' => 'We could not save your enquiry just now. Please send it again.']);
                }

                $held[] = $name;
            }

            return $step();
        } finally {
            foreach ($held as $name) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
            }
        }
    }

    /**
     * A filled hidden field, or a form sent faster than a person can fill it in. Checked after the
     * details are valid, so a person who sends too early sees what is missing rather than a thank-you.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException when the form's timing note is missing or forged
     */
    private function looksAutomated(array $input): bool
    {
        if (filled($input['website'] ?? null)) {
            return true;
        }

        try {
            $startedAt = (int) Crypt::decryptString((string) ($input['started'] ?? ''));
        } catch (DecryptException) {
            throw ValidationException::withMessages(['form' => 'The form expired. Please send it again.']);
        }

        return now()->getTimestamp() - $startedAt < EnquiryForm::MINIMUM_SECONDS;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalized(array $input): array
    {
        $text = fn (mixed $value): mixed => is_string($value) ? trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value)) : $value;
        $input = array_map($text, $input);

        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower($input['email']);
        }

        if (is_string($input['phone'] ?? null) && $input['phone'] !== '') {
            $input['phone'] = $this->e164($input['phone']) ?? $input['phone'];
        }

        return array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);
    }

    /**
     * An Indian or international number in E.164 form, or null when it cannot be read as one.
     */
    private function e164(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        return match (true) {
            str_starts_with(trim($phone), '+') => '+'.$digits,
            strlen($digits) === 10 && preg_match('/^[6-9]/', $digits) === 1 => '+91'.$digits,
            strlen($digits) === 11 && str_starts_with($digits, '0') => '+91'.substr($digits, 1),
            strlen($digits) === 12 && str_starts_with($digits, '91') => '+'.$digits,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $plain = fn (bool $required, int $max): array => [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[<>]/'];

        return [
            'name' => $plain(true, 120),
            'email' => ['nullable', 'required_without:phone', 'string', 'max:191', 'email:rfc'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'company' => $plain(false, 120),
            'team_size' => ['nullable', Rule::in(array_keys(EnquiryForm::TEAM_SIZES))],
            'segment' => ['nullable', 'integer', Rule::exists('customer_segments', 'id')->where('is_active', true)],
            'message' => ['nullable', 'string', 'max:2000'],
            'property' => ['nullable', 'integer'],
            'workspace' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'consent' => ['accepted'],
            'from' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s]*$/'],
            ...collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])->mapWithKeys(fn (string $key): array => [$key => $plain(false, 120)])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'email.required_without' => 'Enter an email address or a phone number, so SmartGro can reply.',
            'email.email' => 'Enter an email address in the format name@company.com.',
            'phone.regex' => 'Enter a phone number such as 98765 43210, or with its country code, such as +44 20 7946 0000.',
            'consent.accepted' => 'Tick the box so SmartGro may contact you about this enquiry.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
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

    /**
     * At most a few enquiries a day from the same email or phone number, on top of the per-address
     * limit on the route.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function limitPerContact(array $validated): void
    {
        foreach (array_filter([$validated['email'] ?? null, $validated['phone'] ?? null]) as $identifier) {
            $key = 'enquiries:contact:'.hash_hmac('sha256', (string) $identifier, (string) config('app.key'));

            if (RateLimiter::tooManyAttempts($key, self::MAX_PER_CONTACT_PER_DAY)) {
                throw ValidationException::withMessages(['form' => 'We have already received several enquiries from these details today. SmartGro will be in touch.']);
            }

            RateLimiter::hit($key, 86400);
        }
    }

    /**
     * The existing contact with the same email or phone, unchanged, or a new one.
     *
     * @param  array<string, mixed>  $validated
     */
    private function contact(array $validated): Contact
    {
        $existing = Contact::query()
            ->where(fn ($query) => $query
                ->when(isset($validated['email']), fn ($query) => $query->orWhere('email', $validated['email']))
                ->when(isset($validated['phone']), fn ($query) => $query->orWhere('phone_e164', $validated['phone'])))
            ->lockForUpdate()->oldest('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        $contact = (new Contact)->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone_e164' => $validated['phone'] ?? null,
            'company_name' => $validated['company'] ?? null,
        ]);
        $contact->save();

        return $contact;
    }

    /**
     * A reference that does not reveal how many enquiries exist and avoids look-alike characters,
     * e.g. ENQ-7K3QX9MP.
     */
    private function reference(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $reference = 'ENQ-'.implode('', array_map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 8)));
        } while (Lead::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
