<?php

namespace App\Models;

use App\Actions\Content\RedirectMovedPage;
use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use App\Services\Content\PagePayload;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * A website page. The row holds only the published version; drafts and their review are revisions
 * (ADR-0005). `status` is the live state: `draft` until the first publication, then `published`.
 */
class Page extends Model implements Revisionable
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasRevisions;

    /**
     * The payload keys a page revision carries.
     *
     * @var list<string>
     */
    private const CONTENT_FIELDS = ['title', 'slug', 'template', 'blocks'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    /**
     * The live search-engine metadata, written when a version is published.
     *
     * @return MorphOne<SeoMetadata, $this>
     */
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
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

    /**
     * The public address, or null before the first publication.
     */
    public function path(): ?string
    {
        return $this->isPublished() ? '/'.$this->slug : null;
    }

    public function revisionCapabilities(): array
    {
        return [
            'view' => Capability::ViewContent,
            'edit' => Capability::EditContent,
            'approve' => Capability::ApproveContent,
            'publish' => Capability::PublishContent,
        ];
    }

    public function toRevisionPayload(): array
    {
        $seo = $this->seo()->first();

        return [
            ...$this->only(self::CONTENT_FIELDS),
            'seo' => [
                'title' => $seo?->meta_title,
                'description' => $seo?->meta_description,
                'indexable' => $seo?->isIndexable() ?? true,
            ],
        ];
    }

    public function prepareRevisionPayload(array $payload, bool $forSubmission): array
    {
        return app(PagePayload::class)->validate($this, $payload, $forSubmission);
    }

    /**
     * Puts the version live with its search-engine metadata and, when a published page changes address,
     * redirects the old address (BR-CMS-6). Runs inside the publishing transaction with this row locked.
     *
     * @throws ValidationException when another page already uses the version's address
     */
    public function applyPublishedRevision(Revision $revision): void
    {
        $previousPath = $this->path();
        $content = array_intersect_key($revision->payload, array_flip(self::CONTENT_FIELDS));

        if (filled($content['slug'] ?? null) && static::query()->whereKeyNot($this->getKey())->where('slug', $content['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another page already uses this address.']);
        }

        try {
            $this->forceFill([
                ...$content,
                'status' => 'published',
                'published_revision_id' => $revision->getKey(),
                'published_at' => $revision->published_at,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Another page already uses this address.']);
        }

        $seo = $revision->payload['seo'] ?? [];
        $this->seo()->updateOrCreate([], [
            'meta_title' => $seo['title'] ?? null,
            'meta_description' => $seo['description'] ?? null,
            'robots' => ($seo['indexable'] ?? true) ? SeoMetadata::INDEX : SeoMetadata::NOINDEX,
        ]);

        app(RedirectMovedPage::class)->handle($this, $previousPath, $revision);
    }
}
