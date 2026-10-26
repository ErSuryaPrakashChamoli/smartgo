<?php

namespace App\Models\Concerns;

use App\Actions\Content\RedirectMovedPage;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The Revisionable behaviour of simple publishable content (posts, FAQs, testimonials, case studies):
 * the live row holds the published fields; a payload is validated by the model's rules; publishing
 * copies the fields and, when an address changes, redirects the old one (BR-CMS-6).
 *
 * The model lists its fields in revisionFields() and returns its rules from revisionRules().
 */
trait PublishesRevisionFields
{
    /**
     * @return list<string>
     */
    abstract protected function revisionFields(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rules: array<string, mixed>, messages?: array<string, string>, attributes?: array<string, string>}
     */
    abstract protected function revisionRules(array $payload, bool $forSubmission): array;

    /**
     * Fields kept in a version's payload but not copied to the live row, such as a confirmation that
     * only matters for that version.
     *
     * @return list<string>
     */
    protected function payloadOnlyFields(): array
    {
        return [];
    }

    /**
     * Runs after a version's fields are copied to the live row, inside the publishing transaction.
     */
    protected function revisionPublished(Revision $revision): void {}

    /**
     * Prepares raw input for validation, e.g. turns keyed lists into plain lists.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function payloadForValidation(array $payload): array
    {
        return $payload;
    }

    /**
     * Adjusts the validated payload before it is stored (lists, sanitising).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeRevisionPayload(array $payload): array
    {
        return $payload;
    }

    /**
     * The public address, or null for content that has none or is not published.
     */
    public function path(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function toRevisionPayload(): array
    {
        return [...$this->only($this->revisionFields()), ...array_fill_keys($this->payloadOnlyFields(), null)];
    }

    public function prepareRevisionPayload(array $payload, bool $forSubmission): array
    {
        $payload = $this->payloadForValidation($payload);
        $definition = $this->revisionRules($payload, $forSubmission);
        $validated = Validator::make($payload, $definition['rules'], $definition['messages'] ?? [], $definition['attributes'] ?? [])->validate();

        $fields = [...$this->revisionFields(), ...$this->payloadOnlyFields()];

        return $this->normalizeRevisionPayload(array_merge(
            array_fill_keys($fields, null),
            Arr::only($validated, $fields),
        ));
    }

    /**
     * @throws ValidationException when the version's address is already taken
     */
    public function applyPublishedRevision(Revision $revision): void
    {
        $previousPath = $this->path();

        try {
            $this->forceFill([
                ...Arr::only($revision->payload, $this->revisionFields()),
                'status' => 'published',
                'published_revision_id' => $revision->getKey(),
                'published_at' => $revision->published_at,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Another item already uses this address.']);
        }

        if ($this->path() !== null) {
            app(RedirectMovedPage::class)->handle($this, $previousPath, $this->path(), $revision);
        }

        $this->revisionPublished($revision);
    }
}
