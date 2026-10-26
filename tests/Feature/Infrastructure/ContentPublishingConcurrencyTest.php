<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concurrency\PublishingHarness;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    PublishingHarness::reset();
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

afterEach(function () {
    PublishingHarness::reset();
});

function concurrentlyApproved(Page $page, User $author, User $approver, string $slug): Revision
{
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Offices', 'slug' => $slug, 'template' => 'standard',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Managed offices.</p>']]],
    ]);

    return app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);
}

/**
 * @param  array<string, array{outcome: string, message: string|null}>  $results
 * @return array<string, int>
 */
function publishingTally(array $results): array
{
    return collect($results)->countBy('outcome')->sortKeys()->all();
}

it('publishes an approved version exactly once when people and the scheduler publish it at the same instant', function () {
    $page = Page::factory()->create();
    $revision = concurrentlyApproved($page, $this->author, $this->approver, 'offices');
    $revision->forceFill(['scheduled_for' => now()->subMinute(), 'scheduled_by' => $this->publisher->id])->save();
    $secondSuperAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $this->publisher->id],
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $this->author->id],
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $secondSuperAdmin->id],
        ['mode' => 'scheduled'],
    ]);

    expect(collect($results)->where('outcome', 'published')->count())->toBe(1)
        ->and(collect($results)->where('outcome', 'refused')->pluck('message')->unique()->all())->toBe(['Only an approved version can be published.'])
        ->and($revision->fresh()->state)->toBe(RevisionState::Published)
        ->and($page->fresh()->published_revision_id)->toBe($revision->id)
        ->and(Activity::query()->where('event', 'revision_published')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->count())->toBe(0);
})->group('mysql', 'concurrency');

it('skips, without recording a failure, a scheduled version someone published a moment earlier', function () {
    $page = Page::factory()->create();
    $revision = concurrentlyApproved($page, $this->author, $this->approver, 'offices');
    $revision->forceFill(['scheduled_for' => now()->subMinute(), 'scheduled_by' => $this->publisher->id])->save();

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $this->author->id],
        ['mode' => 'scheduled', 'start_delay_ms' => 50],
    ]);

    expect(publishingTally($results))->toBe(['published' => 1, 'skipped' => 1])
        ->and($results['worker-0']['outcome'])->toBe('published')
        ->and($revision->fresh()->published_by)->toBe($this->author->id)
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->count())->toBe(0);
})->group('mysql', 'concurrency');

it('lets only one of two pages take the same address at the same instant', function () {
    $first = concurrentlyApproved(Page::factory()->create(), $this->author, $this->approver, 'offices');
    $second = concurrentlyApproved(Page::factory()->create(), $this->author, $this->approver, 'offices');

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $first->id, 'user_id' => $this->publisher->id],
        ['mode' => 'manual', 'revision_id' => $second->id, 'user_id' => $this->publisher->id],
    ]);

    expect(publishingTally($results))->toBe(['published' => 1, 'refused' => 1])
        ->and(collect($results)->firstWhere('outcome', 'refused')['message'])->toBe('Another page already uses this address.')
        ->and(Page::query()->where('slug', 'offices')->count())->toBe(1)
        ->and(Revision::query()->where('state', RevisionState::Published)->count())->toBe(1);
})->group('mysql', 'concurrency');
