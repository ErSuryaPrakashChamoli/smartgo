<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Media\CreateMediaAsset;
use App\Actions\Media\DeleteMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Http\Controllers\Backoffice\TwinModelFileController;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->model = uploadTwinModelForTest($this->marketing, $this->property, ['title' => 'Ground floor (test)']);
});

/**
 * A floor plan image in the media library, as the marketing user adds it.
 *
 * @param  array<string, mixed>  $overrides
 */
function floorPlanImageForTest(MediaOrigin $origin, ?Property $depicts, array $overrides = []): MediaAsset
{
    return app(CreateMediaAsset::class)->handle(test()->marketing, UploadedFile::fake()->image('plan.png', 1600, 1000), [
        'title' => 'Floor plan', 'origin' => $origin->value, 'alt_text' => 'Plan of a floor', 'not_from_old_website' => true,
        'depicts_property_id' => $depicts?->getKey(), ...$overrides,
    ]);
}

/**
 * @return array{label: string, media_asset_id: int, note: string|null, is_example: bool}
 */
function floorPlanEntryForTest(MediaAsset $asset, string $label, bool $isExample = false, ?string $note = null): array
{
    return ['label' => $label, 'media_asset_id' => $asset->id, 'note' => $note, 'is_example' => $isExample];
}

it('shows a published profile\'s walkthrough and floor plans on the location page, with nothing 3D loaded until asked for (OD-181)', function () {
    $plan = floorPlanImageForTest(MediaOrigin::Commissioned, $this->property, ['alt_text' => 'Plan of the ground floor of A-70', 'credit_line' => 'Plan: Test Architects']);
    $example = floorPlanImageForTest(MediaOrigin::Render, null, ['alt_text' => 'Example plan of an invented floor']);
    publishPropertyProfileForTest($this->property, [
        'twin_model_id' => $this->model->id,
        'twin_tour_stops' => [['camera' => 'SG_CAM_2', 'title' => 'Meeting room'], ['camera' => 'SG_CAM_1', 'title' => 'Reception']],
        'floor_plans' => [floorPlanEntryForTest($plan, 'Ground floor', note: 'Reception and four cabins.'), floorPlanEntryForTest($example, 'Level 2', isExample: true)],
    ]);

    $response = get('/locations/noida/a-70')
        ->assertOk()
        ->assertSee('href="#explore"', false)
        ->assertSee('Explore SmartGro A-70')
        ->assertSee('3D model of SmartGro A-70')
        ->assertSee('Ground floor (test)')
        ->assertSee('Conceptual illustration — not a scan of this location')
        ->assertSee('data-model="/3d-models/'.$this->model->id.'"', false)
        ->assertSeeInOrder(['data-twin-camera="SG_CAM_2"', 'Meeting room', 'data-twin-camera="SG_CAM_1"', 'Reception'], false)
        ->assertSeeInOrder(['Ground floor', 'Level 2'])
        ->assertSee('Reception and four cabins.')
        ->assertSee('Plan: Test Architects')
        ->assertSee('alt="Plan of the ground floor of A-70"', false)
        ->assertSee('src="'.$plan->renditionPath('w960').'"', false);

    expect(substr_count($response->getContent(), e("Example floor plan — not SmartGro's layout")))->toBe(1)
        ->and(preg_match('/<(script|link)\b[^>]*twin-viewer/', $response->getContent()))->toBe(0);
});

it('shows nothing to explore without a walkthrough or floor plans', function () {
    publishPropertyProfileForTest($this->property);

    get('/locations/noida/a-70')->assertOk()->assertDontSee('href="#explore"', false)->assertDontSee('data-twin-walkthrough', false)->assertDontSee('data-floor-plans', false);
});

it('leaves out a model, guided views and floor plans the location may no longer show', function () {
    $other = Property::query()->where('code', 'H-105')->sole();
    $good = floorPlanImageForTest(MediaOrigin::ClientPhotography, $this->property);
    publishPropertyProfileForTest($this->property, [
        'twin_model_id' => uploadTwinModelForTest($this->marketing, $other)->id,
        'floor_plans' => [
            floorPlanEntryForTest(floorPlanImageForTest(MediaOrigin::Commissioned, $other), 'Plan of another location'),
            floorPlanEntryForTest(floorPlanImageForTest(MediaOrigin::Render, $this->property), 'Render shown as a real plan'),
            floorPlanEntryForTest(floorPlanImageForTest(MediaOrigin::ClientPhotography, $this->property), 'Photo marked as an example', isExample: true),
            floorPlanEntryForTest(floorPlanImageForTest(MediaOrigin::Commissioned, $this->property, ['licence_expires_at' => '2020-01-01']), 'Plan with an expired licence'),
            floorPlanEntryForTest($good, 'The one plan that qualifies'),
        ],
    ]);

    get('/locations/noida/a-70')->assertOk()
        ->assertDontSee('data-twin-walkthrough', false)
        ->assertSee('The one plan that qualifies')
        ->assertDontSee(['Plan of another location', 'Render shown as a real plan', 'Photo marked as an example', 'Plan with an expired licence']);

    publishPropertyProfileForTest($this->property, ['twin_model_id' => $this->model->id, 'twin_tour_stops' => [['camera' => 'SG_CAM_9', 'title' => 'A view the model lost']], 'floor_plans' => []]);

    get('/locations/noida/a-70')->assertOk()
        ->assertDontSee('A view the model lost')
        ->assertSeeInOrder(['data-twin-camera="SG_CAM_1"', 'View 1', 'data-twin-camera="SG_CAM_2"', 'View 2'], false);
});

it('makes a floor plan image public only while the location page shows it, and keeps it while a version uses it', function () {
    $plan = floorPlanImageForTest(MediaOrigin::Commissioned, $this->property);
    publishPropertyProfileForTest($this->property, ['floor_plans' => [floorPlanEntryForTest($plan, 'Ground floor')]]);

    get($plan->renditionPath('w960'))->assertOk()->assertHeader('Content-Type', 'image/webp');

    $this->property->profile->forceFill(['status' => 'draft'])->save();

    get($plan->renditionPath('w960'))->assertNotFound();
    expect(fn () => app(DeleteMediaAsset::class)->handle($plan, User::factory()->withRole(StaffRole::SuperAdmin)->create()))
        ->toThrow(ValidationException::class, 'This image is used by a page or a page version, so it cannot be deleted.');
});

it('previews a version\'s walkthrough for staff from the back office\'s file route, before it is public', function () {
    publishPropertyProfileForTest($this->property);
    $draft = app(CreateDraft::class)->handle($this->property->profile, $this->marketing, [
        'headline' => 'H', 'summary' => 'S', 'twin_model_id' => $this->model->id, 'twin_tour_stops' => [['camera' => 'SG_CAM_1', 'title' => 'Reception']],
    ]);

    actingAs($this->marketing)->get(RevisionPreviewController::url($draft))
        ->assertOk()
        ->assertSee('data-model="'.route(TwinModelFileController::ROUTE, ['twinModel' => $this->model], absolute: false).'"', false)
        ->assertSee('Reception');
    get('/locations/noida/a-70')->assertDontSee('data-twin-walkthrough', false);
    get(route('twin-models.show', ['twinModel' => $this->model]))->assertNotFound();
});

it('links the home page to a location\'s walkthrough once one is published, and says it is not available before', function () {
    Property::query()->each(fn (Property $property) => publishPropertyProfileForTest($property));

    get('/')->assertOk()->assertSee('Not available yet')->assertDontSee('#explore', false);

    publishPropertyProfileForTest($this->property, ['twin_model_id' => $this->model->id]);

    get('/')->assertOk()
        ->assertDontSee('Not available yet')
        ->assertSee('href="/locations/noida/a-70#explore"', false)
        ->assertSee('Explore SmartGro A-70 in 3D');
});

it('keeps the location pages, home page and profile editor working without the OD-181 columns, and never queries them', function () {
    publishPropertyProfileForTest($this->property);
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => ! ($table === 'property_profiles' && array_intersect($columns, PropertyProfile::EXPLORE_FIELDS) !== []) && $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = new ArrayObject;
    DB::listen(function ($query) use ($queries): void {
        if (preg_match('/twin_model_id|twin_tour_stops|floor_plans/', $query->sql) === 1) {
            $queries[] = $query->sql;
        }
    });

    get('/locations/noida/a-70')->assertOk()->assertDontSee('href="#explore"', false);
    get('/')->assertOk()->assertSee('Not available yet');
    get(route('twin-models.show', ['twinModel' => $this->model]))->assertNotFound();
    actingAs($this->marketing)->get(PropertyProfileResource::getUrl('edit', ['record' => $this->property->profile]))->assertOk()->assertDontSee('3D walkthrough and floor plans');
    $draft = app(CreateDraft::class)->handle($this->property->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S', 'twin_model_id' => $this->model->id]);

    expect($draft->payload)->not->toHaveKeys(PropertyProfile::EXPLORE_FIELDS)
        ->and($queries->getArrayCopy())->toBe([]);
});
