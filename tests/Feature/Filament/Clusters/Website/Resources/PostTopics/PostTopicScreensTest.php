<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\Posts\Pages\CreatePost;
use App\Filament\Clusters\Website\Resources\Posts\Pages\EditPost;
use App\Filament\Clusters\Website\Resources\PostTopics\Pages\CreatePostTopic;
use App\Filament\Clusters\Website\Resources\PostTopics\Pages\ListPostTopics;
use App\Filament\Clusters\Website\Resources\PostTopics\PostTopicResource;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Revision;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
});

/**
 * Publishes a draft through the workflow: submitted by its author, the editor, approved by a second
 * Marketing Manager and published by the Super Admin (author ≠ approver ≠ publisher).
 */
function publishDraftForTopicTest(Revision $draft): void
{
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, test()->editor), $approver), $publisher);
}

it('adds a topic, audited, and refuses duplicates, markup and malformed addresses', function () {
    actingAs($this->editor);

    Livewire::test(CreatePostTopic::class)
        ->fillForm(['name' => 'Flexible work', 'slug' => 'flexible-work'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PostTopic::query()->sole())->name->toBe('Flexible work')->slug->toBe('flexible-work')
        ->and(Activity::query()->where('subject_type', (new PostTopic)->getMorphClass())->where('event', 'created')->exists())->toBeTrue();

    Livewire::test(CreatePostTopic::class)
        ->fillForm(['name' => 'Flexible work', 'slug' => 'Flexible Work!'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique', 'slug' => 'regex']);

    Livewire::test(CreatePostTopic::class)
        ->fillForm(['name' => 'Work <b>life</b>', 'slug' => 'flexible-work'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'not_regex', 'slug' => 'unique']);

    expect(PostTopic::query()->count())->toBe(1);
});

it('keeps the list of topics to staff who edit website content', function (StaffRole $role) {
    $topic = PostTopic::factory()->create();
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create());

    get(PostTopicResource::getUrl())->assertForbidden();
    get(PostTopicResource::getUrl('create'))->assertForbidden();
    get(PostTopicResource::getUrl('edit', ['record' => $topic]))->assertForbidden();
})->with([StaffRole::Finance, StaffRole::SalesCrmExecutive, StaffRole::OperationsManager]);

it('refuses a deactivated account', function () {
    $topic = PostTopic::factory()->create();
    $this->editor->update(['is_active' => false]);
    actingAs($this->editor);

    get(PostTopicResource::getUrl())->assertForbidden();
    get(PostTopicResource::getUrl('edit', ['record' => $topic]))->assertForbidden();
});

it('chooses topics on an article version, up to three that exist', function () {
    $topics = PostTopic::factory()->count(4)->create();
    actingAs($this->editor);
    $undoBuilderFake = Builder::fake();

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Choosing a workspace', 'slug' => 'choosing-a-workspace', 'topic_ids' => [$topics[1]->id, $topics[0]->id], 'blocks' => []])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoBuilderFake();

    expect(Post::query()->sole()->revisions()->sole()->payload['topic_ids'])->toBe([$topics[1]->id, $topics[0]->id]);
    expect(fn () => app(CreateDraft::class)->handle(Post::factory()->create(), $this->editor, ['topic_ids' => $topics->pluck('id')->all()]))
        ->toThrow(ValidationException::class, 'Choose at most 3 topics.');
    expect(fn () => app(CreateDraft::class)->handle(Post::factory()->create(), $this->editor, ['topic_ids' => [999999]]))
        ->toThrow(ValidationException::class, 'A chosen topic no longer exists.');
});

it('puts a topic on the website only when an article version with it is published', function () {
    $topic = PostTopic::factory()->create(['name' => 'Flexible work', 'slug' => 'flexible-work']);
    $post = Post::factory()->create();
    $draft = app(CreateDraft::class)->handle($post, $this->editor, ['title' => 'On flexible work', 'slug' => 'on-flexible-work', 'excerpt' => 'Why it suits small teams.', 'topic_ids' => [$topic->id], 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text.</p>']]]]);

    get('/insights/topic/flexible-work')->assertNotFound();

    publishDraftForTopicTest($draft);

    expect($post->fresh()->topic_ids)->toBe([$topic->id]);
    get('/insights/topic/flexible-work')->assertOk()->assertSee('On flexible work');
    get('/insights/on-flexible-work')->assertOk()->assertSee('href="/insights/topic/flexible-work"', false);
});

it('fixes a topic shown on the website and keeps any topic in use from being deleted', function () {
    $live = PostTopic::factory()->create();
    $inDraft = PostTopic::factory()->create();
    $unused = PostTopic::factory()->create();
    Post::factory()->published()->create(['topic_ids' => [$live->id]]);
    app(CreateDraft::class)->handle(Post::factory()->create(), $this->editor, ['topic_ids' => [$inDraft->id]]);
    actingAs($this->editor);

    Livewire::test(ListPostTopics::class)
        ->assertCanSeeTableRecords([$live, $inDraft, $unused])
        ->assertActionHidden(TestAction::make('edit')->table($live))
        ->assertActionVisible(TestAction::make('edit')->table($inDraft))
        ->assertActionHidden(TestAction::make('delete')->table($live))
        ->assertActionHidden(TestAction::make('delete')->table($inDraft))
        ->callAction(TestAction::make('delete')->table($unused));

    get(PostTopicResource::getUrl('edit', ['record' => $live]))->assertForbidden();
    expect(PostTopic::query()->pluck('id')->sort()->values()->all())->toBe(collect([$live->id, $inDraft->id])->sort()->values()->all());
});

it('changes an article\'s topics on the website only when a version is published, and drops them once it is no longer published', function () {
    $first = PostTopic::factory()->create(['slug' => 'flexible-work']);
    $second = PostTopic::factory()->create(['slug' => 'meeting-rooms']);
    $post = Post::factory()->create();
    $payload = ['title' => 'On flexible work', 'slug' => 'on-flexible-work', 'excerpt' => 'Why it suits small teams.', 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text.</p>']]]];
    publishDraftForTopicTest(app(CreateDraft::class)->handle($post, $this->editor, [...$payload, 'topic_ids' => [$first->id]]));

    $next = app(CreateDraft::class)->handle($post->fresh(), $this->editor, [...$payload, 'topic_ids' => [$second->id]]);

    get('/insights/topic/flexible-work')->assertOk();
    get('/insights/topic/meeting-rooms')->assertNotFound();
    get('/insights')->assertSee('href="/insights/topic/flexible-work"', false)->assertDontSee('href="/insights/topic/meeting-rooms"', false);

    publishDraftForTopicTest($next);

    get('/insights/topic/flexible-work')->assertNotFound();
    get('/insights/topic/meeting-rooms')->assertOk()->assertSee('On flexible work');
    get('/insights/on-flexible-work')->assertSee('href="/insights/topic/meeting-rooms"', false)->assertDontSee('href="/insights/topic/flexible-work"', false);

    $post->fresh()->forceFill(['status' => 'unpublished'])->save();

    get('/insights/topic/meeting-rooms')->assertNotFound();
    get('/insights')->assertDontSee('aria-label="Topics"', false);
});

it('lists topics with the same number of queries however many there are', function () {
    actingAs($this->editor);
    $addTopicsInEveryState = function (): void {
        [$live, $inDraft] = PostTopic::factory()->count(3)->create()->all();
        Post::factory()->published()->create(['topic_ids' => [$live->id]]);
        app(CreateDraft::class)->handle(Post::factory()->create(), $this->editor, ['topic_ids' => [$inDraft->id]]);
    };
    $queriesToList = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListPostTopics::class)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $addTopicsInEveryState();
    $queriesToList();
    $withFew = $queriesToList();
    $addTopicsInEveryState();
    $addTopicsInEveryState();

    expect($queriesToList())->toBe($withFew);
});

it('keeps the insights pages, the article editor and publishing working on a database without topics, such as the owner\'s local one (OD-120)', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'post_topics' && $schema->hasTable($table));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    $topicQueries = [];
    DB::listen(function ($query) use (&$topicQueries): void {
        if (str_contains($query->sql, 'topic')) {
            $topicQueries[] = $query->sql;
        }
    });
    $post = Post::factory()->create();
    $draft = app(CreateDraft::class)->handle($post, $this->editor, ['title' => 'On flexible work', 'slug' => 'on-flexible-work', 'excerpt' => 'Why it suits small teams.', 'topic_ids' => [5], 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text.</p>']]]]);

    publishDraftForTopicTest($draft);

    get('/insights')->assertOk()->assertSee('On flexible work')->assertDontSee('aria-label="Topics"', false);
    get('/insights/on-flexible-work')->assertOk()->assertSee('On flexible work');
    get('/insights/topic/flexible-work')->assertNotFound();
    get('/sitemap.xml')->assertOk()->assertSee('/insights/on-flexible-work');
    actingAs($this->editor);
    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertOk()->assertFormFieldHidden('topic_ids');
    get(PostTopicResource::getUrl())->assertForbidden();

    expect($topicQueries)->toBe([])
        ->and($draft->fresh()->payload)->not->toHaveKey('topic_ids')
        ->and($post->fresh()->status)->toBe('published');
});
