<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RedirectMatch;
use App\Filament\Clusters\Website\Resources\MissingPages\MissingPageResource;
use App\Filament\Clusters\Website\Resources\MissingPages\Pages\ListMissingPages;
use App\Models\MissingPage;
use App\Models\Redirect;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 04:30:00');
    $this->seed(ReferenceDataSeeder::class);
    // Test files that commit their data (without RefreshDatabase) may have left 404s here.
    MissingPage::query()->delete();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('counts each address that answers "not found", by its path only, with when it was first and last asked for', function () {
    get('/old-offices?email=asha@example.com')->assertNotFound();
    CarbonImmutable::setTestNow('2027-03-02 04:30:00');
    get('/old-offices')->assertNotFound();
    get('/locations/noida/a-71')->assertNotFound();

    expect(MissingPage::query()->orderBy('path')->get()->map->only(['path', 'hits'])->all())->toBe([
        ['path' => '/locations/noida/a-71', 'hits' => 1],
        ['path' => '/old-offices', 'hits' => 2],
    ])->and(MissingPage::query()->where('path', '/old-offices')->sole())
        ->first_seen_at->toDateString()->toBe('2027-03-01')
        ->last_seen_at->toDateString()->toBe('2027-03-02')
        ->and(MissingPage::query()->where('path', 'like', '%asha%')->exists())->toBeFalse();
});

it('does not count pages that exist, removed addresses, other methods, the back office or built assets', function () {
    Redirect::factory()->create(['from_path' => '/pin/', 'match_type' => RedirectMatch::Prefix, 'to_path' => null, 'status_code' => 410]);

    get('/')->assertOk();
    get('/pin/abc')->assertStatus(410);
    post('/no-such-form');
    get('/admin/no-such-screen');
    get('/build/assets/missing.js')->assertNotFound();

    expect(MissingPage::query()->count())->toBe(0);
});

it('stops adding new addresses once the limit is reached, still counting those already listed', function () {
    MissingPage::query()->insert(collect(range(1, MissingPage::MAX_PATHS))->map(fn (int $i): array => ['path' => '/probe-'.$i, 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()])->all());

    get('/one-more')->assertNotFound();
    get('/probe-1')->assertNotFound();

    expect(MissingPage::query()->count())->toBe(MissingPage::MAX_PATHS)
        ->and(MissingPage::query()->where('path', '/probe-1')->value('hits'))->toBe(2);
});

it('answers "not found" as usual and counts nothing on a database without the table, such as the owner\'s server', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'missing_pages' && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();

    get('/old-offices')->assertNotFound();

    expect(DB::table('missing_pages')->count())->toBe(0);
});

it('lists the most asked-for missing addresses to staff who manage redirects, who may dismiss one, audited', function () {
    app(SyncAccessControl::class)->handle();
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $often = MissingPage::query()->forceCreate(['path' => '/old-offices', 'hits' => 12, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $once = MissingPage::query()->forceCreate(['path' => '/typo', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    actingAs($marketing);

    Livewire::test(ListMissingPages::class)
        ->assertCanSeeTableRecords([$often, $once], inOrder: true)
        ->callAction(TestAction::make('dismiss')->table($once))
        ->assertNotified('Dismissed');

    expect(MissingPage::query()->pluck('path')->all())->toBe(['/old-offices'])
        ->and(Activity::query()->where('event', 'missing_page_dismissed')->sole()->properties['path'])->toBe('/typo');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create());
    get(MissingPageResource::getUrl('index'))->assertForbidden();
});
