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
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * An insights article (FR-CMS-7) at /insights/{slug}. The title is the page heading, so the
 * introduction block is not offered. Its topics (up to three) are part of its versions, so they change on
 * the website only when a version is published.
 */
class Post extends Model implements Revisionable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * @var list<string>
     */
    public const array BLOCK_TYPES = ['text', 'image', 'features', 'stats', 'comparison', 'faq', 'cta', 'testimonial'];

    /** Topics one article may have (IA /insights/topic/{slug}). */
    public const int MAX_TOPICS = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['blocks' => 'array', 'topic_ids' => 'array', 'published_at' => 'datetime'];
    }

    public function path(): ?string
    {
        return $this->isPublished() ? '/insights/'.$this->slug : null;
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    /**
     * Topics are left out on a database not migrated for them (PostTopic::isAvailable()).
     */
    protected function revisionFields(): array
    {
        return ['title', 'slug', 'excerpt', ...(PostTopic::isAvailable() ? ['topic_ids'] : []), 'blocks'];
    }

    /**
     * The topics of the published version, in the order chosen; a topic deleted since is left out.
     *
     * @return Collection<int, PostTopic>
     */
    public function topics(): Collection
    {
        return PostTopic::inOrder($this->topic_ids ?? []);
    }

    /**
     * Whether the article is example content: it carries the sample-content topic (OD-143).
     */
    public function isSample(): bool
    {
        $sampleId = PostTopic::sampleId();

        return $sampleId !== null && in_array($sampleId, array_map('intval', $this->topic_ids ?? []), true);
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
                ...(PostTopic::isAvailable() ? [
                    'topic_ids' => ['nullable', 'array', 'max:'.self::MAX_TOPICS],
                    'topic_ids.*' => ['integer', 'distinct', Rule::exists('post_topics', 'id'), ...($forSubmission && ! config('backoffice.sample_content') && PostTopic::sampleId() !== null ? [Rule::notIn([PostTopic::sampleId()])] : [])],
                ] : []),
                ...$blockRules,
            ],
            'messages' => [...$rules->messages(), 'topic_ids.max' => 'Choose at most '.self::MAX_TOPICS.' topics.', 'topic_ids.*.exists' => 'A chosen topic no longer exists.', 'topic_ids.*.not_in' => 'This website does not publish sample articles. Remove the "Sample content" topic and replace the example text first.'],
            'attributes' => $attributes,
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [
            ...$payload,
            ...(PostTopic::isAvailable() ? ['topic_ids' => array_values(array_unique(array_map('intval', $payload['topic_ids'] ?? [])))] : []),
            'blocks' => app(BlockLibrary::class)->normalize($payload['blocks'] ?? []),
        ];
    }
}
