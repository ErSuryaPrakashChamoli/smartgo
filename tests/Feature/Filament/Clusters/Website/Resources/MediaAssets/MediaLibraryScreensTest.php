<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Media\CreateMediaAsset as CreateMediaAssetAction;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();

    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
});

it('lists images for content staff and refuses others', function () {
    $asset = app(CreateMediaAssetAction::class)->handle($this->manager, UploadedFile::fake()->image('a.jpg', 800, 600), [
        'title' => 'Meeting room', 'origin' => 'commissioned', 'alt_text' => 'Meeting room table', 'not_from_old_website' => true,
    ]);
    actingAs($this->manager);

    Livewire::test(ListMediaAssets::class)->assertCanSeeTableRecords([$asset])->assertSee('Commissioned');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())
        ->get(MediaAssetResource::getUrl('index'))
        ->assertForbidden();
});

it('uploads an image through the form with the server-side rules', function () {
    actingAs($this->manager);

    Livewire::test(CreateMediaAsset::class)
        ->fillForm([
            'file' => UploadedFile::fake()->image('lounge.jpg', 1000, 700),
            'origin' => 'client_photography',
            'title' => 'Lounge',
            'alt_text' => 'Lounge with sofas',
            'not_from_old_website' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MediaAsset::query()->sole())->title->toBe('Lounge')->and(MediaAsset::query()->sole()->file())->not->toBeNull();

    Livewire::test(CreateMediaAsset::class)
        ->fillForm(['file' => UploadedFile::fake()->image('b.jpg', 800, 600), 'origin' => 'client_photography', 'title' => 'No alt', 'not_from_old_website' => true])
        ->call('create')
        ->assertHasFormErrors(['alt_text']);
});

it('keeps an image that a page version uses', function () {
    $asset = app(CreateMediaAssetAction::class)->handle($this->manager, UploadedFile::fake()->image('a.jpg', 800, 600), [
        'title' => 'Meeting room', 'origin' => 'commissioned', 'alt_text' => 'Meeting room table', 'not_from_old_website' => true,
    ]);
    app(CreateDraft::class)->handle(Page::factory()->create(), $this->manager, ['title' => 'X', 'slug' => 'x', 'blocks' => [
        ['type' => 'image', 'data' => ['media_asset_id' => $asset->id, 'alt' => 'Table', 'decorative' => false, 'caption' => null]],
    ]]);
    actingAs($this->manager);

    Livewire::test(EditMediaAsset::class, ['record' => $asset->getRouteKey()])
        ->callAction('delete')
        ->assertNotified('Not deleted');

    expect($asset->fresh())->not->toBeNull();
});
