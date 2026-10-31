<?php

namespace App\Models;

use App\Enums\Content\RevisionState;
use Database\Factories\PostTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An insight topic (IA /insights/topic/{slug}; ERD C-31). Marketing keeps the list; none is preset. A
 * topic reaches the website only through articles published with it, which goes through the articles'
 * approval (BR-CMS-1). So once a published article shows it, its name and address stay as they are; and it
 * can be deleted only while no article uses it, published or in a version still being worked on. Changes
 * are audited.
 */
class PostTopic extends Model
{
    /** @use HasFactory<PostTopicFactory> */
    use HasFactory, LogsActivity;

    /**
     * Versions of an article still being worked on, whose topics must exist when they are published.
     *
     * @var list<RevisionState>
     */
    private const array WORKING_STATES = [RevisionState::Draft, RevisionState::Submitted, RevisionState::Approved, RevisionState::Rejected];

    /**
     * The fields the back-office form sets.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'slug'];

    /**
     * Whether this database has topics: on a database not migrated for them (OD-120), the pages, the article
     * editor and publishing must work as before. Asked once a request.
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable((new self)->getTable()) && Schema::hasColumn((new Post)->getTable(), 'topic_ids'));
    }

    /**
     * The topic that marks an article as example content for the team to replace (OD-143). An article
     * with it shows a "Sample content" notice and is never indexed; removing the topic, through the usual
     * approval, makes it an ordinary article.
     */
    public const string SAMPLE_SLUG = 'sample-content';

    /**
     * The ID of the sample-content topic, or null when it does not exist or topics are not available.
     */
    public static function sampleId(): ?int
    {
        return once(fn (): ?int => self::isAvailable() ? self::query()->where('slug', self::SAMPLE_SLUG)->value('id') : null);
    }

    /**
     * Whether this is the sample-content topic, whose page lists only example articles (OD-143).
     */
    public function isSample(): bool
    {
        return $this->slug === self::SAMPLE_SLUG;
    }

    /**
     * The topics with these ids, in the order given; an id whose topic was deleted is left out.
     *
     * @param  list<int>  $ids
     * @return Collection<int, PostTopic>
     */
    public static function inOrder(array $ids): Collection
    {
        if (! self::isAvailable()) {
            return new Collection;
        }

        return static::query()->whereKey($ids)->get()->sortBy(fn (PostTopic $topic): int|false => array_search($topic->getKey(), $ids, true))->values();
    }

    public function path(): string
    {
        return '/insights/topic/'.$this->slug;
    }

    /**
     * Adds, in the same query, how many published articles show each topic and how many article
     * versions still being worked on use it, so a list of topics and its permission checks need no
     * query per topic.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithUseCounts(Builder $query): void
    {
        $query->select($query->qualifyColumn('*'))
            ->selectSub(Post::query()->published()->selectRaw('COUNT(*)')->whereRaw('JSON_CONTAINS(posts.topic_ids, JSON_ARRAY(post_topics.id))'), 'published_posts_count')
            ->selectSub(self::workingPostVersions()->selectRaw('COUNT(*)')->whereRaw("JSON_CONTAINS(JSON_EXTRACT(revisions.payload, '$.topic_ids'), JSON_ARRAY(post_topics.id))"), 'working_versions_count');
    }

    /**
     * Published articles with this topic.
     *
     * @return Builder<Post>
     */
    public function publishedPosts(): Builder
    {
        return Post::query()->published()->whereJsonContains('topic_ids', $this->getKey());
    }

    /**
     * How many published articles show this topic.
     */
    public function publishedPostCount(): int
    {
        return $this->hasAttribute('published_posts_count') ? (int) $this->published_posts_count : $this->publishedPosts()->count();
    }

    /**
     * Whether a published article shows this topic, which fixes its name and address.
     */
    public function isOnWebsite(): bool
    {
        return $this->hasAttribute('published_posts_count') ? $this->published_posts_count > 0 : $this->publishedPosts()->exists();
    }

    /**
     * Whether any article uses this topic: published, or in a version still being worked on.
     */
    public function isUsed(): bool
    {
        if ($this->isOnWebsite()) {
            return true;
        }

        return $this->hasAttribute('working_versions_count')
            ? $this->working_versions_count > 0
            : self::workingPostVersions()->whereJsonContains('payload->topic_ids', $this->getKey())->exists();
    }

    /**
     * @return Builder<Revision>
     */
    private static function workingPostVersions(): Builder
    {
        return Revision::query()->where('revisionable_type', (new Post)->getMorphClass())->whereIn('state', self::WORKING_STATES);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('content');
    }
}
