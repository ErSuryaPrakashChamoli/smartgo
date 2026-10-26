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
     * Layouts a page may use. The landing and legal layouts arrive with Phase 6 and step 4.5.
     *
     * @var list<string>
     */
    public const array TEMPLATES = ['standard'];

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
     * @return array{title: ?string, slug: ?string, template: string, blocks: list<array{type: string, data: array<string, mixed>}>}
     *
     * @throws ValidationException
     */
    public function validate(Page $page, array $payload, bool $forSubmission): array
    {
        $payload['blocks'] = is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : ($payload['blocks'] ?? []);
        $payload['template'] ??= self::TEMPLATES[0];

        $required = $forSubmission ? 'required' : 'nullable';
        ['rules' => $blockRules, 'attributes' => $blockAttributes] = $this->blockLibrary->rules(is_array($payload['blocks']) ? $payload['blocks'] : [], $forSubmission);

        $validated = Validator::make($payload, [
            'title' => [$required, 'string', 'max:120'],
            'slug' => [$required, 'string', 'max:150', 'regex:'.self::SLUG_PATTERN, $this->notReservedRule(), Rule::unique('pages', 'slug')->ignore($page->getKey())],
            'template' => ['required', Rule::in(self::TEMPLATES)],
            ...$blockRules,
            ...($forSubmission ? ['blocks' => [...$blockRules['blocks'], 'min:1']] : []),
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and hyphens, with / between parts (for example legal/privacy).',
            'slug.unique' => 'Another page already uses this address.',
            'blocks.required' => 'Add at least one section.',
            'blocks.min' => 'Add at least one section.',
            'blocks.max' => 'A page can have at most '.BlockLibrary::MAX_BLOCKS.' sections.',
            'blocks.*.type.in' => 'This kind of section is not available.',
        ], [
            'title' => 'page title',
            'slug' => 'page address',
            ...$blockAttributes,
        ])->validate();

        return [
            'title' => $validated['title'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'template' => $validated['template'],
            'blocks' => $this->blockLibrary->normalize($validated['blocks'] ?? []),
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
