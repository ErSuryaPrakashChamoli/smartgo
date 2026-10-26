<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\RestoreRevision;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $this->menu = NavigationMenu::query()->where('handle', 'main')->sole();
    $this->about = Page::factory()->published()->create(['title' => 'About', 'slug' => 'about']);
});

/**
 * @param  list<array<string, mixed>>  $items
 */
function approvedMenu(NavigationMenu $menu, User $author, User $approver, array $items): Revision
{
    $draft = app(CreateDraft::class)->handle($menu, $author, ['items' => $items]);

    return app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);
}

/**
 * @return list<array<string, mixed>>
 */
function sampleMenuItems(Page $page, string $firstLabel = 'About us'): array
{
    return [
        ['label' => $firstLabel, 'type' => 'page', 'page_id' => $page->id, 'url' => null],
        ['label' => 'Company', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => [
            ['label' => 'Partner site', 'type' => 'url', 'page_id' => null, 'url' => 'https://partner.example/smartgro'],
        ]],
    ];
}

it('publishes a menu written by one person, approved by a second and published by a third', function () {
    $revision = approvedMenu($this->menu, $this->author, $this->approver, sampleMenuItems($this->about));

    app(PublishRevision::class)->handle($revision, $this->publisher);

    $items = NavigationItem::query()->where('navigation_menu_id', $this->menu->id)->orderBy('id')->get();

    expect($this->menu->fresh())
        ->published_revision_id->toBe($revision->id)
        ->and($items->pluck('label')->all())->toBe(['About us', 'Company', 'Partner site'])
        ->and($items->pluck('link_type')->all())->toBe(['page', 'group', 'external'])
        ->and($items[2]->parent_id)->toBe($items[1]->id)
        ->and($items[0]->linkable_id)->toBe($this->about->id)
        ->and($this->menu->fresh()->toRevisionPayload())->toEqual($revision->fresh()->payload);
});

it('keeps the separation of duties for menus, including for the Super Admin', function () {
    $draft = app(CreateDraft::class)->handle($this->menu, $this->publisher, ['items' => sampleMenuItems($this->about)]);
    app(SubmitRevision::class)->handle($draft, $this->publisher);

    expect(fn () => app(ApproveRevision::class)->handle($draft, $this->publisher))
        ->toThrow(AuthorizationException::class, 'You worked on this version, so someone else must review it.');

    app(ApproveRevision::class)->handle($draft, $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($draft, $this->approver))
        ->toThrow(AuthorizationException::class, 'You approved this version, so someone else must publish it.')
        ->and($this->menu->fresh()->published_revision_id)->toBeNull();
});

it('replaces the whole live menu when a new version is published', function () {
    app(PublishRevision::class)->handle(approvedMenu($this->menu, $this->author, $this->approver, sampleMenuItems($this->about)), $this->publisher);
    app(PublishRevision::class)->handle(approvedMenu($this->menu, $this->author, $this->approver, [
        ['label' => 'Only link', 'type' => 'page', 'page_id' => $this->about->id, 'url' => null],
    ]), $this->publisher);

    expect(NavigationItem::query()->where('navigation_menu_id', $this->menu->id)->pluck('label')->all())->toBe(['Only link']);
});

it('refuses to submit a menu that breaks its rules', function () {
    $draft = app(CreateDraft::class)->handle($this->menu, $this->author, ['items' => [
        ['label' => 'Empty group', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => []],
    ]]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class)
        ->and($draft->fresh()->state)->toBe(RevisionState::Draft);
});

it('refuses staff without content permissions', function () {
    expect(fn () => app(CreateDraft::class)->handle($this->menu, User::factory()->withRole(StaffRole::Finance)->create(), ['items' => []]))
        ->toThrow(AuthorizationException::class, 'You cannot edit this content.');
});

it('publishes a scheduled menu as the person who scheduled it', function () {
    $revision = approvedMenu($this->menu, $this->author, $this->approver, sampleMenuItems($this->about));
    app(ScheduleRevision::class)->handle($revision, $this->publisher, now()->addHour());
    $this->travel(1)->hours();

    $this->artisan('content:publish-scheduled')->expectsOutputToContain('Published 1 scheduled version(s).');

    expect($revision->fresh())->state->toBe(RevisionState::Published)->published_by->toBe($this->publisher->id);
});

it('restores an earlier menu as a new draft and audits each step', function () {
    $first = app(PublishRevision::class)->handle(approvedMenu($this->menu, $this->author, $this->approver, sampleMenuItems($this->about)), $this->publisher);
    app(PublishRevision::class)->handle(approvedMenu($this->menu, $this->author, $this->approver, sampleMenuItems($this->about, 'Renamed')), $this->publisher);

    $restored = app(RestoreRevision::class)->handle($first->fresh(), $this->publisher);

    expect($restored)->state->toBe(RevisionState::Draft)->payload->toEqual($first->fresh()->payload)
        ->and(NavigationItem::query()->where('navigation_menu_id', $this->menu->id)->value('label'))->toBe('Renamed')
        ->and(Activity::query()->inLog('content')->where('subject_type', (new Revision)->getMorphClass())->whereIn('subject_id', [$first->id])->pluck('event')->all())
        ->toBe(['revision_created', 'revision_submitted', 'revision_approved', 'revision_published']);
});
