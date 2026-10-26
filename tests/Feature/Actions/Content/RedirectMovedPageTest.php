<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->page = Page::factory()->create();
    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

function approvedPageAddress(Page $page, User $author, User $approver, string $slug): Revision
{
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Offices', 'slug' => $slug, 'template' => 'standard',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Managed offices.</p>']]],
    ]);

    return app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);
}

function publishPageAddress(Page $page, string $slug): void
{
    app(PublishRevision::class)->handle(approvedPageAddress($page, test()->author, test()->approver, $slug), test()->publisher);
}

/**
 * @return array<string, string|null>
 */
function redirectMap(): array
{
    return Redirect::query()->orderBy('from_path')->pluck('to_path', 'from_path')->all();
}

it('creates no redirect when a page is first published', function () {
    publishPageAddress($this->page, 'offices');

    expect(Redirect::query()->count())->toBe(0);
});

it('redirects the old address permanently once a new address is published', function () {
    publishPageAddress($this->page, 'offices');
    publishPageAddress($this->page, 'managed-offices');

    expect(redirectMap())->toBe(['/offices' => '/managed-offices'])
        ->and(Redirect::query()->sole())->status_code->toBe(301)->created_by->toBe($this->publisher->id);

    get('/offices')->assertStatus(301)->assertRedirect('/managed-offices');
    get('/managed-offices')->assertOk();
});

it('does not redirect while the new address is only submitted or approved', function () {
    publishPageAddress($this->page, 'offices');
    approvedPageAddress($this->page, $this->author, $this->approver, 'managed-offices');

    expect(Redirect::query()->count())->toBe(0);

    get('/offices')->assertOk();
    get('/managed-offices')->assertNotFound();
});

it('keeps every old address one hop from the page through several moves', function () {
    publishPageAddress($this->page, 'a');
    publishPageAddress($this->page, 'b');
    publishPageAddress($this->page, 'c');

    expect(redirectMap())->toBe(['/a' => '/c', '/b' => '/c']);

    get('/a')->assertRedirect('/c');
    get('/b')->assertRedirect('/c');
});

it('never forms a loop when a page moves back to an earlier address', function () {
    publishPageAddress($this->page, 'a');
    publishPageAddress($this->page, 'b');
    publishPageAddress($this->page, 'a');

    expect(redirectMap())->toBe(['/b' => '/a']);

    publishPageAddress($this->page, 'c');
    publishPageAddress($this->page, 'b');

    $redirects = redirectMap();

    expect($redirects)->toBe(['/a' => '/b', '/c' => '/b'])
        ->and(array_intersect(array_values($redirects), array_keys($redirects)))->toBe([]);

    get('/b')->assertOk();
});

it('removes an old redirect when another page takes over its address', function () {
    publishPageAddress($this->page, 'offices');
    publishPageAddress($this->page, 'managed-offices');
    $otherPage = Page::factory()->create();

    publishPageAddress($otherPage, 'offices');

    expect(redirectMap())->toBe([]);

    get('/offices')->assertOk();
    get('/managed-offices')->assertOk();
});

it('audits each redirect change with the publisher and the page version', function () {
    publishPageAddress($this->page, 'offices');
    publishPageAddress($this->page, 'managed-offices');

    $entry = Activity::query()->inLog('content')->where('event', 'redirect_created')->sole();

    expect($entry->causer_id)->toBe($this->publisher->id)
        ->and($entry->properties->only(['from', 'to', 'status_code', 'page', 'revision'])->all())->toEqual([
            'from' => '/offices',
            'to' => '/managed-offices',
            'status_code' => 301,
            'page' => 'Page #'.$this->page->id,
            'revision' => 2,
        ]);
});
