<?php

namespace App\Services\Content;

use Illuminate\Validation\Rule;

/**
 * Rules shared by the content types: plain text, single-part addresses and published contact details.
 */
class ContentRules
{
    /**
     * Digits, spaces, brackets and hyphens, optionally starting with +.
     */
    public const string PHONE_PATTERN = '/^\+?[0-9][0-9 ()-]{6,19}$/';

    /**
     * A full international number: + and the country code, digits only.
     */
    public const string WHATSAPP_PATTERN = '/^\+[1-9][0-9]{7,14}$/';

    /**
     * Lowercase words joined by hyphens, e.g. `flexible-workspace-guide`.
     */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return list<mixed>
     */
    public function plainText(bool $required, int $max): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.$max, 'not_regex:/[<>]/'];
    }

    /**
     * @return list<mixed>
     */
    public function slug(bool $required, string $table, mixed $ignoreId): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:150', 'regex:'.self::SLUG_PATTERN, Rule::unique($table, 'slug')->ignore($ignoreId)];
    }

    /**
     * A phone number, WhatsApp number and email address as the website shows them, all optional.
     *
     * @return array{phone: list<string>, email: list<string>, whatsapp: list<string>}
     */
    public function contactDetails(): array
    {
        return [
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'email' => ['nullable', 'string', 'max:120', 'email:rfc'],
            'whatsapp' => ['nullable', 'string', 'regex:'.self::WHATSAPP_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
            'slug.regex' => 'Use lowercase letters, numbers and hyphens (for example flexible-workspace-guide).',
            'slug.unique' => 'Another item already uses this address.',
            'consent_on_file.accepted' => 'Consent from the person or company must be on file before this can be published (BR-CMS-7).',
            'statement_verified.accepted' => 'Confirm that this is a real, unedited statement.',
            'blocks.required' => 'Add at least one section.',
            'blocks.min' => 'Add at least one section.',
            'blocks.*.type.in' => 'This kind of section is not available here.',
            'phone.regex' => 'Use digits, spaces, brackets and hyphens, optionally starting with + (for example +91 120 000 0000).',
            'whatsapp.regex' => 'Use the full international number: + and the country code, digits only (for example +911200000000).',
        ];
    }
}
