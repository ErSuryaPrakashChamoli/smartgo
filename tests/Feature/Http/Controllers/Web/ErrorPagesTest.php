<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RedirectMatch;
use App\Models\Redirect;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Content\SiteNavigation;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('answers an unknown address with the website\'s own not-found page, never indexed and without echoing the address', function () {
    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill(['values' => ['email' => 'hello@example.com'], 'published_revision_id' => Revision::factory()->create()->id])->save();

    get('/no-such-page-%3Cscript%3Ealert(1)%3C%2Fscript%3E?token=query-value-1234')
        ->assertNotFound()
        ->assertSee('Page not found</h1>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('aria-label="SmartGro home"', false)
        ->assertSee('href="mailto:hello@example.com"', false)
        ->assertSee('href="/locations"', false)
        ->assertDontSee('<script>alert(1)', false)
        ->assertDontSee('no-such-page')
        ->assertDontSee('query-value-1234');
});

it('answers a removed address with the website\'s own gone page', function () {
    Redirect::factory()->create(['from_path' => '/pin/', 'match_type' => RedirectMatch::Prefix, 'to_path' => null, 'status_code' => 410]);

    get('/pin/old-spam')->assertStatus(410)->assertSee('This page has been removed</h1>', false);
});

it('still shows the not-found page when the published menus cannot be read', function () {
    $this->mock(SiteNavigation::class)->shouldReceive('present')->andThrow(new RuntimeException('Menus unavailable'));

    get('/no-such-page')->assertNotFound()->assertSee('Page not found</h1>', false)->assertDontSee('Menus unavailable');
});

it('shows the server error page without reading the database', function () {
    (new RegisterErrorViewPaths)();
    DB::enableQueryLog();

    $html = view('errors::500')->render();

    expect($html)->toContain('Something went wrong</h1>')
        ->and($html)->toContain('noindex, nofollow')
        ->and(DB::getQueryLog())->toBe([]);
});

/**
 * Maintenance mode for this test only. The test environment's driver is the file in the shared
 * storage folder, so activating it for real would also take down any local server using that folder.
 */
function fakeMaintenanceMode(): void
{
    app()->instance(MaintenanceMode::class, new class implements MaintenanceMode
    {
        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        public function data(): array
        {
            return [];
        }
    });
}

it('answers each visitor error with its real status and the website\'s own page, never its internal message', function (int $status, string $title) {
    Route::middleware('web')->get('/test-only/status/{status}', fn (int $status) => abort($status, 'Internal detail for '.$status));

    get('/test-only/status/'.$status)
        ->assertStatus($status)
        ->assertSee($title.'</h1>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('Error '.$status)
        ->assertDontSee('Internal detail')
        ->assertDontSee('test-only');
})->with([
    'forbidden' => [403, 'You cannot open this page'],
    'expired' => [419, 'This page has expired'],
    'too many requests' => [429, 'Too many requests'],
    'unavailable' => [503, 'Back soon'],
]);

it('never shows the details of a server error', function () {
    config(['app.debug' => false, 'logging.default' => 'null']);
    Route::middleware('web')->get('/test-only/failure', fn () => throw new RuntimeException('SQLSTATE[HY000] password=hunter2 in '.base_path('app/Secret.php')));

    get('/test-only/failure')
        ->assertStatus(500)
        ->assertSee('Something went wrong</h1>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('hunter2')
        ->assertDontSee(base_path())
        ->assertDontSee('RuntimeException');
});

it('answers every address with the maintenance page while the website is down, without reading the database', function () {
    fakeMaintenanceMode();
    DB::enableQueryLog();

    foreach (['/', '/locations', '/admin'] as $path) {
        get($path)->assertStatus(503)->assertSee('Back soon</h1>', false)->assertSee('noindex, nofollow', false);
    }

    expect(DB::getQueryLog())->toBe([]);
});

it('keeps error pages for errors: a normal page never carries an error heading', function () {
    get('/locations')->assertOk()->assertDontSee('Error 404')->assertDontSee('noindex');
});

it('answers back-office errors with the same pages, and sends guests to sign in', function () {
    app(SyncAccessControl::class)->handle();
    $sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create();
    $admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();

    get('/admin/sales/closing-reasons')->assertRedirect('/admin/login');

    actingAs($sales)->get('/admin/sales/closing-reasons')
        ->assertForbidden()
        ->assertSee('You cannot open this page</h1>', false)
        ->assertSee('noindex, nofollow', false);

    actingAs($admin)->get('/admin/sales/enquiries/999999')
        ->assertNotFound()
        ->assertSee('Page not found</h1>', false)
        ->assertDontSee('No query results')
        ->assertDontSee('App\\Models');
});
