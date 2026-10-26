<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function revisionCapabilities(): array
    {
        return [
            'edit' => Capability::EditContent,
            'approve' => Capability::ApproveContent,
            'publish' => Capability::PublishContent,
        ];
    }

    public function toRevisionPayload(): array
    {
        return $this->only(self::CONTENT_FIELDS);
    }

    /**
     * @throws ValidationException when another page already uses the version's address
     */
    public function applyPublishedRevision(Revision $revision): void
    {
        $content = array_intersect_key($revision->payload, array_flip(self::CONTENT_FIELDS));

        if (filled($content['slug'] ?? null) && static::query()->whereKeyNot($this->getKey())->where('slug', $content['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'Another page already uses this address.']);
        }

        $this->forceFill([
            ...$content,
            'status' => 'published',
            'published_revision_id' => $revision->getKey(),
            'published_at' => $revision->published_at,
        ])->save();
    }
}
