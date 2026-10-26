<?php

namespace App\Services\Content;

use App\Models\Page;
use Closure;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Server-side validation of a page revision's payload. Every draft save, submission and publication
 * goes through it, whichever screen or service starts the change.
 */
class PagePayload
{
    /**
     * Layouts a page may use. `legal` pages hold text sections only and need a legal review reference
     * before submission (D-15, OD-97); the landing layout arrives with Phase 6.
     *
     * @var list<string>
     */
    public const array TEMPLATES = ['standard', 'legal'];

    /**
     * Lowercase words joined by hyphens, with / between parts, e.g. `about` or `legal/privacy`.
     */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/';

    /**
     * First path parts kept for the website's own templates (INFORMATION_ARCHITECTURE §2). Paths of
     * registered routes, such as the back office and design previews, are refused as well.
     *
     * @var list<string>
     */
    private const RESERVED_SEGMENTS = ['workspaces', 'locations', 'insights', 'customers', 'booking', 'book-a-tour', 'enquire', 'offers', 'sitemap.xml', 'robots.txt'];

    public function __construct(private BlockLibrary $blockLibrary) {}

    /**
     * Returns the payload as it is stored: only page fields, blocks as a list, rich text sanitised.
     *
     * @param  array<string, mixed>  $payload
     * @return array{title: ?string, slug: ?string, template: string, legal_review_reference: ?string, blocks: list<array{type: string, data: array<string, mixed>}>, seo: array{title: ?string, description: ?string, indexable: bool, image_asset_id: ?int}}
     *
     * @throws ValidationException
     */
    public function validate(Page $page, array $payload, bool $forSubmission): array
    {
        $payload['blocks'] = is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : ($payload['blocks'] ?? []);
        $payload['template'] ??= self::TEMPLATES[0];

        $required = $forSubmission ? 'required' : 'nullable';
        $isLegal = $payload['template'] === 'legal';
        ['rules' => $blockRules, 'attributes' => $blockAttributes] = $this->blockLibrary->rules(is_array($payload['blocks']) ? $payload['blocks'] : [], $forSubmission, $isLegal ? ['text'] : null);

        $validated = Validator::make($payload, [
            'title' => [$required, 'string', 'max:120'],
            'slug' => [$required, 'string', 'max:150', 'regex:'.self::SLUG_PATTERN, $this->notReservedRule(), Rule::unique('pages', 'slug')->ignore($page->getKey())],
            'template' => ['required', Rule::in(self::TEMPLATES)],
            'legal_review_reference' => [$forSubmission && $isLegal ? 'required' : 'nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'seo' => ['nullable', 'array:title,description,indexable,image_asset_id'],
            'seo.title' => ['nullable', 'string', 'max:60', 'not_regex:/[<>]/'],
            'seo.description' => ['nullable', 'string', 'max:160', 'not_regex:/[<>]/'],
            'seo.indexable' => ['nullable', 'boolean'],
            'seo.image_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            ...$blockRules,
        ], [
            'legal_review_reference.required' => 'Legal pages need the reference of their review by counsel before they can be submitted (D-15).',
            'slug.regex' => 'Use lowercase letters, numbers and hyphens, with / between parts (for example legal/privacy).',
            'slug.unique' => 'Another page already uses this address.',
            'blocks.required' => 'Add at least one section.',
            'blocks.min' => 'Add at least one section.',
            'blocks.max' => 'A page can have at most '.BlockLibrary::MAX_BLOCKS.' sections.',
            'blocks.*.type.in' => 'This kind of section is not available.',
            'seo.title.not_regex' => 'Use plain text: < and > are not allowed.',
            'seo.description.not_regex' => 'Use plain text: < and > are not allowed.',
        ], [
            'title' => 'page title',
            'slug' => 'page address',
            'seo.title' => 'title in search results',
            'seo.description' => 'description in search results',
            ...$blockAttributes,
        ])->validate();

        return [
            'title' => $validated['title'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'template' => $validated['template'],
            'legal_review_reference' => $isLegal ? ($validated['legal_review_reference'] ?? null) : null,
            'blocks' => $this->blockLibrary->normalize($validated['blocks'] ?? []),
            'seo' => [
                'title' => $validated['seo']['title'] ?? null,
                'description' => $validated['seo']['description'] ?? null,
                'indexable' => (bool) ($validated['seo']['indexable'] ?? true),
                'image_asset_id' => isset($validated['seo']['image_asset_id']) ? (int) $validated['seo']['image_asset_id'] : null,
            ],
        ];
    }

    /**
     * Whether the address belongs to a template or route the website already uses.
     */
    public function isReserved(string $slug): bool
    {
        $segment = strtolower(explode('/', $slug)[0]);

        return in_array($segment, self::RESERVED_SEGMENTS, true) || in_array($segment, $this->routedSegments(), true);
    }

    private function notReservedRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $this->isReserved($value)) {
                $fail('This address is used by another part of the website. Choose a different one.');
            }
        };
    }

    /**
     * @return list<string>
     */
    private function routedSegments(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RegisteredRoute $route): bool => $route->isFallback)
            ->map(fn (RegisteredRoute $route): string => strtolower(explode('/', trim($route->uri(), '/'))[0]))
            ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'))
            ->unique()
            ->values()
            ->all();
    }
}
