<?php

namespace App\Services\Content;

use App\Enums\Content\RedirectMatch;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\RedirectMap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Server-side rules for the redirects made by hand (FR-SEO-4, FR-SEO-6, OD-66). Every address is a
 * path on this website, so no redirect can send visitors elsewhere; every redirect reaches its target
 * in one hop, so no chain or loop can form; and a redirect that another part of the website would
 * always answer first is refused, so the list never holds dead rules.
 */
class RedirectMapPayload
{
    /**
     * The most redirects the list may hold (OD-99).
     */
    public const int MAX_REDIRECTS = 500;

    /**
     * Returns the list as it is stored: plain rows in order, without a target for 410.
     *
     * @param  array<string, mixed>  $payload
     * @return array{redirects: list<array{from_path: string, match_type: string, status_code: int, to_path: string|null}>}
     *
     * @throws ValidationException
     */
    public function validate(RedirectMap $map, array $payload, bool $forSubmission): array
    {
        $redirects = is_array($payload['redirects'] ?? null) ? array_values($payload['redirects']) : [];
        $payload = ['redirects' => array_map(fn (mixed $row): mixed => is_array($row) ? array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $row) : $row, $redirects)];

        $validator = Validator::make($payload, [
            'redirects' => ['present', 'array', 'list', 'max:'.self::MAX_REDIRECTS],
            'redirects.*' => ['required', 'array:from_path,match_type,status_code,to_path'],
            'redirects.*.match_type' => ['required', Rule::enum(RedirectMatch::class)],
            'redirects.*.status_code' => ['required', 'integer', Rule::in([301, 302, 410])],
            'redirects.*.from_path' => ['required', 'string', 'max:255', $this->pathRule()],
            'redirects.*.to_path' => ['nullable', 'string', 'max:255', 'required_unless:redirects.*.status_code,410', 'prohibited_if:redirects.*.status_code,410', $this->pathRule()],
        ], [
            'redirects.max' => 'The list can hold at most '.self::MAX_REDIRECTS.' redirects.',
            'redirects.*.to_path.required_unless' => 'Enter where the redirect goes.',
            'redirects.*.to_path.prohibited_if' => 'A 410 (gone) answer has no target. Leave it empty.',
        ], [
            'redirects.*.from_path' => 'old address',
            'redirects.*.to_path' => 'new address',
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($payload, $forSubmission): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkConsistency($validator, $payload['redirects'], $forSubmission);
        });

        $validated = $validator->validate();

        return ['redirects' => array_map(fn (array $row): array => [
            'from_path' => $row['from_path'],
            'match_type' => $row['match_type'],
            'status_code' => (int) $row['status_code'],
            'to_path' => (int) $row['status_code'] === 410 ? null : $row['to_path'],
        ], $validated['redirects'])];
    }

    /**
     * Duplicates, conflicts with the website and other redirects, and targets that would chain or loop.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function checkConsistency(ValidatorInstance $validator, array $rows, bool $forSubmission): void
    {
        $automatic = Redirect::query()->where('source', Redirect::SOURCE_AUTOMATIC)->get(['from_path', 'match_type'])
            ->map(fn (Redirect $redirect): array => ['from_path' => $redirect->from_path, 'match_type' => $redirect->match_type->value]);
        $sources = collect($rows)->map(fn (array $row): array => ['from_path' => $row['from_path'], 'match_type' => $row['match_type']])->concat($automatic);
        $seen = [];

        foreach ($rows as $index => $row) {
            $from = $row['from_path'];
            $match = $row['match_type'];
            $key = $match.' '.$from;

            if (isset($seen[$key])) {
                $validator->errors()->add("redirects.{$index}.from_path", 'This address is already in the list.');
            }

            $seen[$key] = true;

            if ($match === RedirectMatch::Exact->value && ($from === '/' || str_ends_with($from, '/'))) {
                $validator->errors()->add("redirects.{$index}.from_path", 'An exact address has no / at the end, and the home page cannot be redirected.');
            }

            if ($match === RedirectMatch::Prefix->value && ($from === '/' || ! str_ends_with($from, '/'))) {
                $validator->errors()->add("redirects.{$index}.from_path", 'A starting address ends with / (for example /pin/), so it never catches a different address such as /pinboard.');
            }

            if ($automatic->contains(fn (array $source): bool => $source['from_path'] === $from && $source['match_type'] === $match)) {
                $validator->errors()->add("redirects.{$index}.from_path", 'A page\'s automatic redirect already starts at this address.');
            }

            if ($this->isAnsweredByWebsite($from, $match)) {
                $validator->errors()->add("redirects.{$index}.from_path", 'Another part of the website answers this address, so the redirect would never be used.');
            }

            $to = $row['to_path'] ?? null;

            if ((int) $row['status_code'] === 410 || ! is_string($to)) {
                continue;
            }

            if ($to === $from || ($match === RedirectMatch::Prefix->value && str_starts_with($to, $from))) {
                $validator->errors()->add("redirects.{$index}.to_path", 'The new address is inside the old one, so visitors would loop.');

                continue;
            }

            $isServed = $this->isAnsweredByWebsite($to, RedirectMatch::Exact->value);

            if (! $isServed && $sources->contains(fn (array $source): bool => $this->matches($source, $to))) {
                $validator->errors()->add("redirects.{$index}.to_path", 'The new address is redirected too. Point to the final address, so visitors arrive in one hop.');
            } elseif ($forSubmission && ! $isServed) {
                $validator->errors()->add("redirects.{$index}.to_path", 'Nothing is published at the new address yet. Publish the page first.');
            }
        }
    }

    /**
     * Whether a route or a published page answers the address before any redirect is consulted.
     */
    private function isAnsweredByWebsite(string $path, string $match): bool
    {
        $candidates = $match === RedirectMatch::Prefix->value ? [rtrim($path, '/'), $path.'any'] : [$path];

        foreach ($candidates as $candidate) {
            try {
                if ($candidate === '' || ! Route::getRoutes()->match(Request::create($candidate))->isFallback) {
                    return true;
                }
            } catch (HttpException) {
                return true;
            }
        }

        return $match === RedirectMatch::Exact->value
            && Page::query()->published()->where('slug', ltrim($path, '/'))->exists();
    }

    /**
     * @param  array{from_path: string, match_type: string}  $source
     */
    private function matches(array $source, string $path): bool
    {
        return $source['match_type'] === RedirectMatch::Prefix->value
            ? str_starts_with($path, $source['from_path'])
            : $source['from_path'] === $path;
    }

    /**
     * A path on this website without a query or fragment.
     */
    private function pathRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! Redirect::isSafePath($value) || str_contains($value, '?') || str_contains($value, '#') || preg_match('/[<>"\']/', $value)) {
                $fail('Use a path on this website starting with /, without the domain, ? or # (for example /old-page).');
            }
        };
    }
}
