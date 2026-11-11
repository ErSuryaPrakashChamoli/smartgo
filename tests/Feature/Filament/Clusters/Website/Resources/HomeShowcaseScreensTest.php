<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\HomeBanners\HomeBannerResource;
use App\Filament\Clusters\Website\Resources\HomeBanners\Pages\CreateHomeBanner;
use App\Filament\Clusters\Website\Resources\HomeHighlights\HomeHighlightResource;
use App\Filament\Clusters\Website\Resources\HomeHighlights\Pages\CreateHomeHighlight;
use App\Filament\Clusters\Website\Resources\HomeHighlights\Pages\EditHomeHighlight;
use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
});

it('lists banners and highlights with what is live and what is in progress, and refuses staff who cannot view content', function (string $resource, string $model, string $field) {
    $live = $model::factory()->published()->create();
    app(CreateDraft::class)->handle($model::factory()->create(), $this->editor, [$field => 'Only in a draft']);

    actingAs($this->editor)->get($resource::getUrl('index'))->assertOk()
        ->assertSee($live->getAttribute($field))->assertSee('Only in a draft')->assertSee('v1: Draft');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())
        ->get($resource::getUrl('index'))->assertForbidden();
})->with([
    'home banners' => [HomeBannerResource::class, HomeBanner::class, 'heading'],
    'highlights' => [HomeHighlightResource::class, HomeHighlight::class, 'text'],
]);

it('creates a banner as an unpublished draft with its image and buttons', function () {
    Storage::fake('local');
    actingAs($this->editor);
    $image = app(CreateMediaAsset::class)->handle($this->editor, UploadedFile::fake()->image('lounge.jpg', 1600, 1000), [
        'title' => 'Lounge', 'origin' => MediaOrigin::LicensedStock->value, 'alt_text' => 'A lounge', 'not_from_old_website' => true,
        'licence_holder' => 'Holder', 'licence_reference' => 'REF-1',
    ]);

    Livewire::test(CreateHomeBanner::class)
        ->fillForm([
            'heading' => 'Work your way', 'text' => 'Desks and offices.', 'media_asset_id' => $image->id, 'sort_order' => 5,
            'primary_link' => ['label' => 'See workspace types', 'type' => 'section', 'section' => 'workspace_types'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HomeBanner::query()->sole())
        ->status->toBe('draft')
        ->heading->toBeNull()
        ->and(HomeBanner::query()->sole()->revisions()->sole())
        ->state->toBe(RevisionState::Draft)
        ->payload->toMatchArray(['heading' => 'Work your way', 'media_asset_id' => $image->id, 'sort_order' => 5, 'secondary_link' => null])
        ->payload->primary_link->toMatchArray(['label' => 'See workspace types', 'type' => 'section', 'section' => 'workspace_types']);
});

it('saves an example highlight as a draft but will not submit it where sample content is not allowed', function () {
    config(['backoffice.sample_content' => false]);
    actingAs($this->editor);

    Livewire::test(CreateHomeHighlight::class)
        ->fillForm(['text' => '24/7 access', 'icon' => 'key', 'is_sample' => true, 'sort_order' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    $highlight = HomeHighlight::query()->sole();

    Livewire::test(EditHomeHighlight::class, ['record' => $highlight->getRouteKey()])
        ->assertSchemaStateSet(['text' => '24/7 access', 'icon' => 'key', 'is_sample' => true])
        ->callAction('submit')
        ->assertHasErrors(['data.is_sample']);

    expect($highlight->revisions()->sole()->state)->toBe(RevisionState::Draft);
});
