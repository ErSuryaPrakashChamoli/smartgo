<?php

namespace App\Services\Content;

use App\Enums\Content\MediaOrigin;
use App\Models\MediaAsset;
use Illuminate\Validation\Rule;

/**
 * Server-side rules for media uploads and descriptions (BR-MED-1, BR-MED-4, BR-MED-5, OD-19). The file
 * type is checked from its content, not its name; SVG is not accepted.
 */
class MediaAssetRules
{
    public const int MAX_KILOBYTES = 10240;

    /**
     * @return array<string, mixed>
     */
    public function file(): array
    {
        return [
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', MediaAsset::ACCEPTED_MIME_TYPES),
                'extensions:jpg,jpeg,png,webp',
                'max:'.self::MAX_KILOBYTES,
                'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000',
            ],
            'origin' => ['required', Rule::enum(MediaOrigin::class)],
            'not_from_old_website' => ['accepted'],
        ];
    }

    /**
     * The property a photo shows; required from users who manage media only for assigned properties.
     *
     * @return array<string, mixed>
     */
    public function property(bool $required): array
    {
        return ['depicts_property_id' => [$required ? 'required' : 'nullable', 'integer', Rule::exists('properties', 'id')]];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(?MediaOrigin $origin): array
    {
        $isLicensed = $origin === MediaOrigin::LicensedStock;

        return [
            'title' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'is_decorative' => ['nullable', 'boolean'],
            'alt_text' => ['nullable', 'string', 'max:150', 'not_regex:/[<>]/', 'required_unless:is_decorative,true'],
            'caption' => ['nullable', 'string', 'max:200', 'not_regex:/[<>]/'],
            'focal_point_x' => ['nullable', 'numeric', 'between:0,1'],
            'focal_point_y' => ['nullable', 'numeric', 'between:0,1'],
            'licence_type' => ['nullable', 'string', 'max:40'],
            'licence_holder' => [$isLicensed ? 'required' : 'nullable', 'string', 'max:120'],
            'licence_reference' => [$isLicensed ? 'required' : 'nullable', 'string', 'max:120'],
            'licence_expires_at' => ['nullable', 'date'],
            'credit_line' => ['nullable', 'string', 'max:150', 'not_regex:/[<>]/'],
            'usage_restrictions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Upload a JPEG, PNG or WebP image. Other file types, including SVG, are not accepted.',
            'file.extensions' => 'Upload a JPEG, PNG or WebP image. Other file types, including SVG, are not accepted.',
            'file.dimensions' => 'The image must be between 200 and 8,000 pixels wide and high.',
            'alt_text.required_unless' => 'Describe what the image shows, or mark it as decorative (BR-MED-4).',
            'not_from_old_website.accepted' => 'Nothing from the old smartgro.space website may be reused (OD-19).',
            'depicts_property_id.required' => 'Choose the property this photo shows.',
            '*.not_regex' => 'Use plain text: < and > are not allowed.',
        ];
    }
}
