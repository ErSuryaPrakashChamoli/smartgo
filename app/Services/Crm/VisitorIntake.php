<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Page;
use App\Services\Website\EnquiryForm;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * How the website takes in a visitor's details, shared by the enquiry form and the tour request so both
 * treat personal data the same way: text cleaned and email and phone normalised; the trap field and the
 * minimum fill time; a few submissions a day per email or phone; a lock per email and phone while the
 * contact is found or added; an existing contact with the same email or phone is reused without being
 * overwritten (BR-CRM-2); and the agreement recorded with the privacy notice shown. Personal data is
 * never written to the logs.
 */
class VisitorIntake
{
    public const int MAX_PER_CONTACT_PER_DAY = 5;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalized(array $input): array
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
     * Plain text: required or not, with a maximum length, and without < or >.
     *
     * @return list<string>
     */
    public function plainText(bool $required, int $max): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[<>]/'];
    }

    /**
     * The visitor's name, email or phone (one is enough) and company. Each form adds its agreement.
     *
     * @return array<string, mixed>
     */
    public function contactRules(): array
    {
        return [
            'name' => $this->plainText(true, 120),
            'email' => ['nullable', 'required_without:phone', 'string', 'max:191', 'email:rfc'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'company' => $this->plainText(false, 120),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function contactMessages(): array
    {
        return [
            'email.required_without' => 'Enter an email address or a phone number, so SmartGro can reply.',
            'email.email' => 'Enter an email address in the format name@company.com.',
            'phone.regex' => 'Enter a phone number such as 98765 43210, or with its country code, such as +44 20 7946 0000.',
            'consent.accepted' => 'Tick the box so SmartGro may contact you about this enquiry.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
        ];
    }

    /**
     * A filled hidden field, or a form sent faster than a person can fill it in. Called after the
     * details are valid, so a person who sends too early sees what is missing rather than a thank-you.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException when the form's timing note is missing or forged
     */
    public function looksAutomated(array $input): bool
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
     * At most a few submissions a day from the same email or phone number, on top of the per-address
     * limit on the route.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    public function limitPerContact(array $validated): void
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
    public function whileHoldingContactLocks(array $validated, callable $step): mixed
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
     * The existing contact with the same email or phone, unchanged, or a new one. Call inside a
     * transaction while holding the contact locks: they already keep submissions with the same email or
     * phone apart, so this is a plain read. A locking read would add gap locks, and two new visitors
     * sending at the same instant would then deadlock on their inserts.
     *
     * @param  array<string, mixed>  $validated
     */
    public function contact(array $validated): Contact
    {
        $existing = Contact::query()
            ->where(fn ($query) => $query
                ->when(isset($validated['email']), fn ($query) => $query->orWhere('email', $validated['email']))
                ->when(isset($validated['phone']), fn ($query) => $query->orWhere('phone_e164', $validated['phone'])))
            ->oldest('id')->first();

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
     * What the visitor agreed to, with the privacy notice version shown and a keyed hash of their address.
     *
     * @return array<string, mixed>
     */
    public function consentSnapshot(Page $notice, ?string $ipAddress): array
    {
        return [
            'purpose' => 'service_communication',
            'text' => EnquiryForm::CONSENT_TEXT,
            'agreed_at' => now()->toIso8601String(),
            'notice_path' => '/'.$notice->slug,
            'notice_page_id' => $notice->getKey(),
            'notice_revision' => $notice->publishedRevision?->number,
            'notice_published_at' => $notice->published_at?->toIso8601String(),
            'marketing' => false,
            'ip_hash' => $ipAddress === null ? null : hash_hmac('sha256', $ipAddress, (string) config('app.key')),
        ];
    }

    /**
     * A reference that does not reveal how many records exist and avoids look-alike characters, e.g.
     * ENQ-7K3QX9MP or TOUR-7K3QX9MP.
     *
     * @param  class-string<Model>  $model
     */
    public function reference(string $prefix, string $model = Lead::class): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $reference = $prefix.'-'.implode('', array_map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 8)));
        } while ($model::query()->where('reference', $reference)->exists());

        return $reference;
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
}
