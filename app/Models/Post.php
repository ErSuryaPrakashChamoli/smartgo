<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Revisionable;
use App\Services\Content\BlockLibrary;
use App\Services\Content\ContentRules;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An insights article (FR-CMS-7) at /insights/{slug}. The title is the page heading, so the
 * introduction block is not offered.
 */
class Post extends Model implements Revisionable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * @var list<string>
     */
    public const array BLOCK_TYPES = ['text', 'image', 'features', 'faq', 'cta', 'testimonial'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['blocks' => 'array', 'published_at' => 'datetime'];
    }

    public function path(): ?string
    {
        return $this->isPublished() ? '/insights/'.$this->slug : null;
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['title', 'slug', 'excerpt', 'blocks'];
    }

    protected function payloadForValidation(array $payload): array
    {
        return [...$payload, 'blocks' => is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : []];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);
        ['rules' => $blockRules, 'attributes' => $attributes] = app(BlockLibrary::class)->rules($payload['blocks'], $forSubmission, self::BLOCK_TYPES);

        return [
            'rules' => [
                'title' => $rules->plainText($forSubmission, 120),
                'slug' => $rules->slug($forSubmission, 'posts', $this->getKey()),
                'excerpt' => $rules->plainText($forSubmission, 300),
                ...$blockRules,
            ],
            'messages' => $rules->messages(),
            'attributes' => $attributes,
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [...$payload, 'blocks' => app(BlockLibrary::class)->normalize($payload['blocks'] ?? [])];
    }
}
