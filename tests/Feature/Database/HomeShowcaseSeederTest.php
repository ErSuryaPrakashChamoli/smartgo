<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\MediaAsset;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\DummyStaffSeeder;
use Database\Seeders\HomeShowcaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);
});

it('publishes the sample banners and example highlights through the workflow, labelled, and adds nothing when run again (OD-169)', function () {
    $sources = collect(HomeShowcaseSeeder::IMAGES)->mapWithKeys(fn (array $image): array => [$image['path'] => hash_file('sha256', base_path($image['path']))]);

    Artisan::call('db:seed', ['--class' => HomeShowcaseSeeder::class]);
    $revisions = Revision::query()->count();
    Artisan::call('db:seed', ['--class' => HomeShowcaseSeeder::class]);

    $author = User::query()->where('email', DummyStaffSeeder::email('marketing'))->sole();
    $approver = User::query()->where('email', DummyStaffSeeder::email('marketing2'))->sole();
    $published = Revision::query()->where('state', RevisionState::Published)->get();

    expect(MediaAsset::query()->count())->toBe(4)
        ->and(MediaAsset::query()->where('origin', MediaOrigin::LicensedStock)->whereNotNull('licence_reference')->count())->toBe(3)
        ->and(HomeBanner::query()->published()->count())->toBe(4)
        ->and(HomeHighlight::query()->published()->where('is_sample', true)->count())->toBe(count(HomeShowcaseSeeder::HIGHLIGHTS))
        ->and($published->every(fn (Revision $revision): bool => $revision->author_id === $author->id && $revision->reviewer_id === $approver->id))->toBeTrue()
        ->and(Revision::query()->count())->toBe($revisions)
        ->and($sources->every(fn (string $hash, string $path): bool => is_file(base_path($path)) && hash_file('sha256', base_path($path)) === $hash))->toBeTrue();

    $html = get('/')->assertOk()->getContent();
    expect($html)->toContain('aria-label="4 of 4"')->toContain('A better space to do your best work.')
        ->toContain('AI-generated image, not a photo of a SmartGro location')->toContain('Stock photo, not a SmartGro location')
        ->toContain('Example highlights')->toContain('High-speed Wi-Fi');
});

it('leaves a banner or highlight alone once someone else has published a version of it', function () {
    Artisan::call('db:seed', ['--class' => HomeShowcaseSeeder::class]);
    $highlight = HomeHighlight::query()->where('text', 'Power backup')->sole();
    $highlight->publishedRevision->forceFill(['author_id' => User::factory()->withEmailAuthentication()->create()->id])->save();
    $highlight->forceFill(['text' => 'Power backup at every location'])->save();

    Artisan::call('db:seed', ['--class' => HomeShowcaseSeeder::class]);

    expect($highlight->refresh()->text)->toBe('Power backup at every location')
        ->and(HomeHighlight::query()->count())->toBe(count(HomeShowcaseSeeder::HIGHLIGHTS));
});

it('does nothing on a database without the banner and highlight tables, and refuses production', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'home_banners' && $schema->hasTable($table));
    Once::flush();
    $seeder = new HomeShowcaseSeeder;
    $staff = User::factory()->withEmailAuthentication()->create();

    expect($seeder->seedWith($staff, $staff, $staff))->toMatchArray(['skipped' => true, 'banners' => 0])
        ->and(MediaAsset::query()->exists())->toBeFalse();

    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => $seeder->seedWith($staff, $staff, $staff))->toThrow(RuntimeException::class, 'local databases only');
});
