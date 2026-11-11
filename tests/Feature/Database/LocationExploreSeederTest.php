<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Enums\Twin\CaptureType;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\Revision;
use App\Models\TwinModel;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DummyStaffSeeder;
use Database\Seeders\ExampleOfficeModel;
use Database\Seeders\LocationExploreSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);
});

/**
 * @return array{author: User, approver: User}
 */
function exploreDemoStaff(): array
{
    return [
        'author' => User::query()->where('email', DummyStaffSeeder::email('marketing'))->sole(),
        'approver' => User::query()->where('email', DummyStaffSeeder::email('marketing2'))->sole(),
    ];
}

it('gives each location a labelled example walkthrough and floor plans through the workflow, and adds nothing when run again (OD-181)', function () {
    config(['backoffice.sample_content' => false]);
    $sources = collect(LocationExploreSeeder::FLOOR_PLANS)->mapWithKeys(fn (array $plan): array => [$plan['file'] => hash_file('sha256', database_path('seeders/media/demo/floor-plans/'.$plan['file']))]);
    Artisan::call('db:seed', ['--class' => DemoContentSeeder::class]);

    Artisan::call('db:seed', ['--class' => LocationExploreSeeder::class]);
    $revisions = Revision::query()->count();
    Artisan::call('db:seed', ['--class' => LocationExploreSeeder::class]);
    Artisan::call('db:seed', ['--class' => DemoContentSeeder::class]);

    $staff = exploreDemoStaff();
    $profiles = PropertyProfile::query()->published()->with('property')->get();
    $a70 = Property::query()->where('code', 'A-70')->sole();

    expect(config('backoffice.sample_content'))->toBeFalse()
        ->and(Revision::query()->count())->toBe($revisions)
        ->and(TwinModel::query()->where('capture_type', CaptureType::ConceptualModel)->count())->toBe(4)
        ->and(TwinModel::query()->get()->every(fn (TwinModel $model): bool => $model->cameraAnchors()->count() === 5 && $model->file() !== null && str_starts_with($model->title, 'Example 3D model:')))->toBeTrue()
        ->and(MediaAsset::query()->where('origin', MediaOrigin::Render)->whereNull('depicts_property_id')->pluck('title')->all())->toEqualCanonicalizing(array_column(LocationExploreSeeder::FLOOR_PLANS, 'title'))
        ->and($sources->every(fn (string $hash, string $file): bool => hash_file('sha256', database_path('seeders/media/demo/floor-plans/'.$file)) === $hash))->toBeTrue()
        ->and($profiles)->toHaveCount(4)
        ->and($profiles->every(fn (PropertyProfile $profile): bool => TwinModel::query()->whereKey($profile->twin_model_id)->where('property_id', $profile->property_id)->exists()
            && count($profile->twin_tour_stops) === 5
            && collect($profile->floor_plans)->pluck('is_example')->all() === [true, true]))->toBeTrue()
        ->and(Revision::query()->where('state', RevisionState::Published)->whereNotNull('payload->twin_model_id')->get()
            ->every(fn (Revision $revision): bool => $revision->author_id === $staff['author']->id && $revision->reviewer_id === $staff['approver']->id))->toBeTrue();

    get($a70->path())->assertOk()
        ->assertSee('3D model of SmartGro A-70')
        ->assertSee('Conceptual illustration — not a scan of this location')
        ->assertSeeInOrder(collect(ExampleOfficeModel::floor(LocationExploreSeeder::MODEL_FLOORS['A-70'])['views'])->pluck('title')->all())
        ->assertSeeInOrder(['Level 1 (example)', 'Level 2 (example)'])
        ->assertSee(e("Example floor plan — not SmartGro's layout"), false);
    get('/3d-models/'.$a70->profile->twin_model_id)->assertOk();
});

it('leaves alone a profile someone else is working on and skips a location that is not on the website', function () {
    Artisan::call('db:seed', ['--class' => DemoContentSeeder::class]);
    $a71 = Property::query()->where('code', 'A-71')->sole();
    app(CreateDraft::class)->handle($a71->profile, User::factory()->withRole(StaffRole::MarketingManager)->create(), ['headline' => 'Their draft', 'summary' => 'S']);
    Property::query()->where('code', 'H-105')->sole()->profile->forceFill(['status' => 'draft'])->save();

    $result = app(LocationExploreSeeder::class)->seedWith(exploreDemoStaff());

    expect($result)->toMatchArray(['models' => 2, 'floor_plans' => 2, 'locations' => 2, 'skipped' => ['H-105'], 'left_alone' => ['PropertyProfile A-71']])
        ->and($a71->profile->fresh()->twin_model_id)->toBeNull()
        ->and($a71->profile->openRevision()->payload['headline'])->toBe('Their draft');
});

it('refuses to run in production', function () {
    Artisan::call('db:seed', ['--class' => DummyStaffSeeder::class]);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(LocationExploreSeeder::class)->seedWith(exploreDemoStaff()))
        ->toThrow(RuntimeException::class, 'The 3D walkthrough and floor plan demo is for local databases only.')
        ->and(TwinModel::query()->exists())->toBeFalse();
});
