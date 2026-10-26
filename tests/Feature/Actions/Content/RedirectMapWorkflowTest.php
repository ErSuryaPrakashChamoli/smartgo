<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\RedirectMap;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $this->map = RedirectMap::query()->sole();
    Page::factory()->published()->create(['slug' => 'workspaces']);
    Page::factory()->published()->create(['slug' => 'contact']);
});

/**
 * @return array{from_path: string, match_type: string, status_code: int, to_path: string|null}
 */
function redirectRow(string $from, ?string $to = '/workspaces', int $status = 301, string $match = 'exact'): array
{
    return ['from_path' => $from, 'match_type' => $match, 'status_code' => $status, 'to_path' => $to];
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function approvedRedirectList(RedirectMap $map, array $rows, User $author, User $approver): Revision
{
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($map, $author, ['redirects' => $rows]), $author);

    return app(ApproveRevision::class)->handle($revision, $approver);
}

it('puts the approved list live: old addresses answer 301 or 410 in one hop', function () {
    $revision = approvedRedirectList($this->map, [
        redirectRow('/coworking-space-noida'),
        redirectRow('/pin/', null, 410, 'prefix'),
    ], $this->author, $this->approver);

    get('/coworking-space-noida')->assertNotFound();

    app(PublishRevision::class)->handle($revision, $this->publisher);

    get('/coworking-space-noida')->assertMovedPermanently()->assertRedirect('/workspaces');
    get('/pin/12345/some-pin')->assertStatus(410);
    expect(Redirect::query()->where('source', Redirect::SOURCE_MANUAL)->count())->toBe(2)
        ->and(Activity::query()->where('event', 'redirect_created')->count())->toBe(2);
});

it('refuses redirects that could leave the website, chain, loop or never be used', function (array $rows, string $field, string $message) {
    Redirect::factory()->create(['from_path' => '/old-about', 'to_path' => '/contact']);

    expect(fn () => app(CreateDraft::class)->handle($this->map, $this->author, ['redirects' => $rows]))
        ->toThrow(function (ValidationException $exception) use ($field, $message): void {
            expect($exception->errors())->toHaveKey($field)
                ->and($exception->errors()[$field][0])->toContain($message);
        });
})->with([
    'another website' => [[redirectRow('/old', 'https://evil.example/x')], 'redirects.0.to_path', 'Use a path on this website'],
    'protocol-relative address' => [[redirectRow('/old', '//evil.example')], 'redirects.0.to_path', 'Use a path on this website'],
    'backslash address' => [[redirectRow('/old', '/\\evil.example')], 'redirects.0.to_path', 'Use a path on this website'],
    'old address with a domain' => [[redirectRow('https://smartgro.space/old')], 'redirects.0.from_path', 'Use a path on this website'],
    'to itself' => [[redirectRow('/old', '/old')], 'redirects.0.to_path', 'visitors would loop'],
    'inside its own prefix' => [[redirectRow('/old/', '/old/new', 301, 'prefix')], 'redirects.0.to_path', 'visitors would loop'],
    'chain in the list' => [[redirectRow('/a', '/b'), redirectRow('/b', '/workspaces')], 'redirects.0.to_path', 'redirected too'],
    'loop in the list' => [[redirectRow('/a', '/b'), redirectRow('/b', '/a')], 'redirects.0.to_path', 'redirected too'],
    'chain through an automatic redirect' => [[redirectRow('/a', '/old-about')], 'redirects.0.to_path', 'redirected too'],
    'same address twice' => [[redirectRow('/a'), redirectRow('/a', '/contact')], 'redirects.1.from_path', 'already in the list'],
    'address of an automatic redirect' => [[redirectRow('/old-about')], 'redirects.0.from_path', 'automatic redirect already starts'],
    'address of a published page' => [[redirectRow('/contact')], 'redirects.0.from_path', 'Another part of the website answers'],
    'address of a route' => [[redirectRow('/insights')], 'redirects.0.from_path', 'Another part of the website answers'],
    'prefix covering a route' => [[redirectRow('/customers/', '/workspaces', 301, 'prefix')], 'redirects.0.from_path', 'Another part of the website answers'],
    'exact address ending in /' => [[redirectRow('/old/')], 'redirects.0.from_path', 'An exact address has no / at the end'],
    'home page' => [[redirectRow('/')], 'redirects.0.from_path', 'An exact address has no / at the end'],
    'prefix without /' => [[redirectRow('/pin', null, 410, 'prefix')], 'redirects.0.from_path', 'A starting address ends with /'],
    '410 with a target' => [[redirectRow('/gone', '/workspaces', 410)], 'redirects.0.to_path', 'has no target'],
    '301 without a target' => [[redirectRow('/gone', null)], 'redirects.0.to_path', 'Enter where the redirect goes'],
]);

it('lets a draft point to a page that is not published yet, but not a submitted list', function () {
    $draft = app(CreateDraft::class)->handle($this->map, $this->author, ['redirects' => [redirectRow('/old', '/new-page')]]);

    app(SubmitRevision::class)->handle($draft, $this->author);
})->throws(ValidationException::class, 'Nothing is published at the new address yet.');

it('removes dropped redirects and keeps the use count of unchanged ones on the next publication', function () {
    app(PublishRevision::class)->handle(approvedRedirectList($this->map, [redirectRow('/keep'), redirectRow('/drop')], $this->author, $this->approver), $this->publisher);
    get('/keep')->assertMovedPermanently();

    app(PublishRevision::class)->handle(approvedRedirectList($this->map->fresh(), [redirectRow('/keep'), redirectRow('/changed', '/contact', 302)], $this->author, $this->approver), $this->publisher);

    get('/drop')->assertNotFound();
    get('/changed')->assertRedirect('/contact')->assertStatus(302);
    expect(Redirect::query()->where('from_path', '/keep')->sole()->hits)->toBe(1)
        ->and(Activity::query()->where('event', 'redirect_removed')->sole()->properties['from'])->toBe('/drop');
});

it('applies the four-eyes rules to the list', function () {
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($this->map, $this->author, ['redirects' => [redirectRow('/old')]]), $this->author);

    expect(fn () => app(ApproveRevision::class)->handle($revision, $this->author))->toThrow(AuthorizationException::class);

    $approved = app(ApproveRevision::class)->handle($revision, $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($approved, $this->approver))->toThrow(AuthorizationException::class);
});

it('checks the list again at publication, when a page has taken an address meanwhile', function () {
    $revision = approvedRedirectList($this->map, [redirectRow('/offers-2026')], $this->author, $this->approver);
    Page::factory()->published()->create(['slug' => 'offers-2026']);

    expect(fn () => app(PublishRevision::class)->handle($revision, $this->publisher))->toThrow(ValidationException::class, 'Another part of the website answers')
        ->and(Redirect::query()->where('source', Redirect::SOURCE_MANUAL)->count())->toBe(0);
});

it('refuses staff without redirect permissions', function () {
    app(CreateDraft::class)->handle($this->map, User::factory()->withRole(StaffRole::Finance)->create(), ['redirects' => []]);
})->throws(AuthorizationException::class);
