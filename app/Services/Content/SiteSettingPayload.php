<?php

namespace App\Services\Content;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Server-side rules for a public settings group (FR-CMS-8, BR-CMS-9). Every value is optional plain
 * text with a strict format, so nothing can inject markup or send visitors to an unexpected website.
 */
class SiteSettingPayload
{
    /**
     * The fields of each group, in the order the editor shows them.
     *
     * @var array<string, list<string>>
     */
    public const array FIELDS = [
        'contact' => ['phone', 'email', 'whatsapp', 'address', 'opening_hours'],
        'social' => ['linkedin', 'instagram', 'facebook', 'x', 'youtube'],
        'tracking' => ['ga4_measurement_id', 'gtm_container_id', 'meta_pixel_id'],
        'legal' => ['legal_name', 'registered_address', 'gstin', 'cin'],
    ];

    /**
     * The website each social link must point to.
     *
     * @var array<string, list<string>>
     */
    public const array SOCIAL_HOSTS = [
        'linkedin' => ['linkedin.com', 'www.linkedin.com'],
        'instagram' => ['instagram.com', 'www.instagram.com'],
        'facebook' => ['facebook.com', 'www.facebook.com'],
        'x' => ['x.com', 'twitter.com'],
        'youtube' => ['youtube.com', 'www.youtube.com'],
    ];

    /**
     * Every field of the group, empty.
     *
     * @return array<string, null>
     */
    public function blank(string $handle): array
    {
        return array_fill_keys(self::FIELDS[$handle], null);
    }

    /**
     * Returns the values as they are stored: every field of the group, trimmed, empty ones as null.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     *
     * @throws ValidationException
     */
    public function validate(SiteSetting $setting, array $payload, bool $forSubmission): array
    {
        $payload = array_map(fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $payload);
        $plainText = fn (int $max, bool $required = false): array => [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[<>]/'];

        $rules = match ($setting->handle) {
            'contact' => [
                'phone' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9 ()-]{6,19}$/'],
                'email' => ['nullable', 'string', 'max:120', 'email:rfc'],
                'whatsapp' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
                'address' => $plainText(300),
                'opening_hours' => $plainText(120),
            ],
            'social' => collect(self::SOCIAL_HOSTS)->map(fn (array $hosts): array => ['nullable', 'string', 'max:255', $this->profileUrlRule($hosts)])->all(),
            'tracking' => [
                'ga4_measurement_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,12}$/'],
                'gtm_container_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,10}$/'],
                'meta_pixel_id' => ['nullable', 'string', 'regex:/^[0-9]{6,20}$/'],
            ],
            'legal' => [
                'legal_name' => $plainText(200, $forSubmission),
                'registered_address' => $plainText(400, $forSubmission),
                'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
                'cin' => ['nullable', 'string', 'regex:/^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/'],
            ],
        };

        $validated = Validator::make($payload, $rules, [
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
            'phone.regex' => 'Use digits, spaces, brackets and hyphens, optionally starting with + (for example +91 120 000 0000).',
            'whatsapp.regex' => 'Use the full international number: + and the country code, digits only (for example +911200000000).',
            'ga4_measurement_id.regex' => 'A Google Analytics 4 measurement ID looks like G-XXXXXXXXXX, in capital letters.',
            'gtm_container_id.regex' => 'A Google Tag Manager container ID looks like GTM-XXXXXXX, in capital letters.',
            'meta_pixel_id.regex' => 'A Meta pixel ID is digits only.',
            'gstin.regex' => 'A GSTIN has 15 characters, in capital letters, for example 09AAAAA0000A1Z5.',
            'cin.regex' => 'A CIN has 21 characters, in capital letters, for example U00000UP2020PTC000000.',
            'legal_name.required' => 'Enter the legal name before submitting the details for verification.',
            'registered_address.required' => 'Enter the registered address before submitting the details for verification.',
        ], [
            'ga4_measurement_id' => 'Google Analytics 4 measurement ID',
            'gtm_container_id' => 'Google Tag Manager container ID',
            'meta_pixel_id' => 'Meta pixel ID',
            'gstin' => 'GSTIN',
            'cin' => 'CIN',
        ])->validate();

        return [...$this->blank($setting->handle), ...Arr::only($validated, self::FIELDS[$setting->handle])];
    }

    /**
     * An https link to a profile on the given website, without user details.
     *
     * @param  list<string>  $hosts
     */
    private function profileUrlRule(array $hosts): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($hosts): void {
            $parts = is_string($value) ? parse_url($value) : false;

            if ($parts === false
                || ($parts['scheme'] ?? null) !== 'https'
                || ! in_array(strtolower($parts['host'] ?? ''), $hosts, true)
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                || trim($parts['path'] ?? '', '/') === ''
                || preg_match('/[\x00-\x20\x7F<>"\\\\]/', $value)) {
                $fail('Use the https address of the profile on '.$hosts[0].', for example https://'.$hosts[0].'/smartgro.');
            }
        };
    }
}
