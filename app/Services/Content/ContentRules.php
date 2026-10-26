<?php

namespace App\Services\Content;

use Illuminate\Validation\Rule;

/**
 * Rules shared by the content types: plain text and single-part addresses.
 */
class ContentRules
{
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
        ];
    }
}
