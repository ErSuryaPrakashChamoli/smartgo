<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\Page;
use App\Models\User;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->page = Page::factory()->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();

    $draft = app(CreateDraft::class)->handle($this->page, $author, ['title' => 'Launch offer', 'slug' => 'launch', 'template' => 'standard', 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Opening soon.</p>']]]]);
    $this->revision = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);
});

it('publishes a scheduled version when its time comes, as the person who scheduled it', function () {
    app(ScheduleRevision::class)->handle($this->revision, $this->publisher, now()->addHour());

    $this->artisan('content:publish-scheduled')->expectsOutputToContain('Published 0 scheduled version(s).')->assertSuccessful();
    expect($this->page->fresh()->published_revision_id)->toBeNull();

    $this->travel(1)->hours();
    $this->artisan('content:publish-scheduled')->expectsOutputToContain('Published 1 scheduled version(s).')->assertSuccessful();

    expect($this->revision->fresh())
        ->state->toBe(RevisionState::Published)
        ->published_by->toBe($this->publisher->id)
        ->scheduled_for->toBeNull()
        ->and($this->page->fresh())
        ->title->toBe('Launch offer')
        ->published_revision_id->toBe($this->revision->id);
});

it('refuses a publication time that is not in the future', function () {
    expect(fn () => app(ScheduleRevision::class)->handle($this->revision, $this->publisher, now()))
        ->toThrow(ValidationException::class, 'Choose a time in the future.')
        ->and($this->revision->fresh()->scheduled_for)->toBeNull();
});

it('leaves a scheduled version unpublished and audits the failure when the scheduler may no longer publish', function () {
    app(ScheduleRevision::class)->handle($this->revision, $this->publisher, now()->addHour());
    $this->publisher->update(['is_active' => false]);
    $this->travel(1)->hours();

    $this->artisan('content:publish-scheduled')->expectsOutputToContain('Published 0 scheduled version(s).')->assertSuccessful();

    $failure = Activity::query()->where('event', 'revision_schedule_failed')->whereMorphedTo('subject', $this->revision)->sole();

    expect($this->revision->fresh())
        ->state->toBe(RevisionState::Approved)
        ->scheduled_for->toBeNull()
        ->and($this->page->fresh()->published_revision_id)->toBeNull()
        ->and($failure->causer_id)->toBe($this->publisher->id)
        ->and($failure->properties['reason'])->toBe('This account is deactivated.');
});

it('leaves a scheduled version unpublished when the scheduler\'s role has lost the publish permission', function () {
    app(ScheduleRevision::class)->handle($this->revision, $this->publisher, now()->addHour());
    Role::findByName(StaffRole::SuperAdmin->value)->revokePermissionTo(Capability::PublishContent);
    $this->travel(1)->hours();

    $this->artisan('content:publish-scheduled')->expectsOutputToContain('Published 0 scheduled version(s).')->assertSuccessful();

    expect($this->revision->fresh()->state)->toBe(RevisionState::Approved)
        ->and($this->page->fresh()->published_revision_id)->toBeNull()
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->sole()->properties['reason'])->toBe('You cannot publish this content.');
});

it('runs every minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->sole(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'content:publish-scheduled'));

    expect($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
