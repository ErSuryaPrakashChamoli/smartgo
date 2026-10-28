<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Media\CreateMediaAsset;
use App\Actions\Media\DeleteMediaAsset;
use App\Actions\Media\UpdateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->assigned = Property::query()->where('code', 'A-70')->sole();
    $this->other = Property::query()->where('code', 'H-105')->sole();
    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $this->manager->assignedProperties()->attach($this->assigned);
    $this->marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function uploadPhoto(User $user, array $overrides = [], ?UploadedFile $file = null): MediaAsset
{
    return app(CreateMediaAsset::class)->handle($user, $file ?? UploadedFile::fake()->image('desk.jpg', 1200, 800), [
        'title' => 'Desk area', 'origin' => MediaOrigin::ClientPhotography->value, 'alt_text' => 'Desks by the window',
        'not_from_old_website' => true, ...$overrides,
    ]);
}

it('lets a Property Manager add photos of an assigned property only, with the property recorded and audited', function () {
    $photo = uploadPhoto($this->manager, ['depicts_property_id' => $this->assigned->id]);

    expect($photo->depicts_property_id)->toBe($this->assigned->id)
        ->and(Activity::query()->where('event', 'media_uploaded')->sole()->properties['property'])->toBe('A-70')
        ->and(fn () => uploadPhoto($this->manager, ['depicts_property_id' => $this->other->id]))->toThrow(ValidationException::class, 'You can only add photos of the properties assigned to you.')
        ->and(fn () => uploadPhoto($this->manager))->toThrow(ValidationException::class, 'Choose the property this photo shows.');
});

it('still checks the file itself for a Property Manager', function () {
    expect(fn () => uploadPhoto($this->manager, ['depicts_property_id' => $this->assigned->id], UploadedFile::fake()->create('photo.jpg', 100, 'application/x-php')))
        ->toThrow(ValidationException::class);
});

it('lets marketing staff add an image of no particular property', function () {
    expect(uploadPhoto($this->marketing)->depicts_property_id)->toBeNull();
});

it('shows a Property Manager only the photos of their properties, and lets them change none of the others', function () {
    $own = uploadPhoto($this->manager, ['depicts_property_id' => $this->assigned->id]);
    $other = uploadPhoto($this->marketing, ['depicts_property_id' => $this->other->id]);
    $general = uploadPhoto($this->marketing);

    actingAs($this->manager);
    Livewire::test(ListMediaAssets::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other, $general]);

    expect($this->manager->can('update', $own))->toBeTrue()
        ->and(fn () => app(UpdateMediaAsset::class)->handle($other, $this->manager, ['title' => 'Mine now', 'alt_text' => 'x']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateMediaAsset::class)->handle($general, $this->manager, ['title' => 'Mine now', 'alt_text' => 'x']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteMediaAsset::class)->handle($other, $this->manager))->toThrow(AuthorizationException::class);
});

it('serves an unpublished photo to no visitor, and to staff only within their scope', function () {
    $own = uploadPhoto($this->manager, ['depicts_property_id' => $this->assigned->id]);
    $other = uploadPhoto($this->marketing, ['depicts_property_id' => $this->other->id]);

    get($own->renditionPath('w480'))->assertNotFound();
    actingAs($this->manager)->get($own->renditionPath('w480'))->assertOk();
    actingAs($this->manager)->get($other->renditionPath('w480'))->assertNotFound();
});

it('never lets the property of a photo change after upload', function () {
    $photo = uploadPhoto($this->marketing, ['depicts_property_id' => $this->assigned->id]);

    app(UpdateMediaAsset::class)->handle($photo, $this->marketing, ['title' => 'Renamed', 'alt_text' => 'Desks', 'depicts_property_id' => $this->other->id]);

    expect($photo->fresh())->title->toBe('Renamed')->depicts_property_id->toBe($this->assigned->id);
});
