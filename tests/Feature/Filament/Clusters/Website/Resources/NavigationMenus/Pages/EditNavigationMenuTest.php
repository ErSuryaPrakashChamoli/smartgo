<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\NavigationMenus\NavigationMenuResource;
use App\Filament\Clusters\Website\Resources\NavigationMenus\Pages\EditNavigationMenu;
use App\Filament\Clusters\Website\Resources\NavigationMenus\Pages\ListNavigationMenus;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Property;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $this->menu = NavigationMenu::query()->where('handle', 'main')->sole();
    $this->about = Page::factory()->published()->create(['title' => 'About SmartGro', 'slug' => 'about']);
});

it('lists the four menus for content staff and refuses everyone else', function () {
    actingAs($this->author);

    Livewire::test(ListNavigationMenus::class)
        ->assertCanSeeTableRecords(NavigationMenu::query()->get())
        ->assertSee(['Main menu', 'Header buttons', 'Footer', 'Legal links', 'Not published']);

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())
        ->get(NavigationMenuResource::getUrl('index'))
        ->assertForbidden();
});

it('takes a menu from draft to the live header with three different people', function () {
    actingAs($this->author);
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(EditNavigationMenu::class, ['record' => $this->menu->getRouteKey()])
        ->callAction('startDraft')
        ->fillForm(['items' => [['label' => 'About us', 'type' => 'page', 'page_id' => $this->about->id, 'url' => null, 'children' => []]]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->callAction('submit')
        ->assertNotified('Submitted for approval');

    actingAs($this->approver);
    Livewire::test(EditNavigationMenu::class, ['record' => $this->menu->getRouteKey()])->callAction('approve')->assertActionDisabled('publish');

    actingAs($this->publisher);
    Livewire::test(EditNavigationMenu::class, ['record' => $this->menu->getRouteKey()])->callAction('publish')->assertNotified('Menu published');
    $undoRepeaterFake();

    expect($this->menu->fresh()->isPublished())->toBeTrue();
    get('/about')->assertSee('About us');
});

it('links items to a part of the website and to a location picked from lists', function () {
    $property = Property::factory()->open()->create();
    actingAs($this->author);
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(EditNavigationMenu::class, ['record' => $this->menu->getRouteKey()])
        ->callAction('startDraft')
        ->fillForm(['items' => [
            ['label' => 'Locations', 'type' => 'section', 'section' => 'locations', 'children' => []],
            ['label' => 'Visit A-70', 'type' => 'property', 'property_id' => $property->id, 'children' => []],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();
    $undoRepeaterFake();

    expect($this->menu->fresh()->workingRevision->payload['items'])
        ->sequence(
            fn ($item) => $item->toMatchArray(['type' => 'section', 'section' => 'locations']),
            fn ($item) => $item->toMatchArray(['type' => 'property', 'property_id' => $property->id]),
        );
});

it('shows a refused destination on the item it belongs to', function () {
    $draft = app(CreateDraft::class)->handle($this->menu, $this->author, ['items' => []]);
    $draft->forceFill(['payload' => ['items' => [['label' => 'Admin', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(1)', 'children' => []]]]])->save();
    actingAs($this->author);

    $editor = Livewire::test(EditNavigationMenu::class, ['record' => $this->menu->getRouteKey()]);
    $itemKey = array_key_first($editor->get('data.items'));

    $editor->call('save')->assertHasFormErrors(["items.{$itemKey}.url"]);

    expect($draft->fresh()->state)->toBe(RevisionState::Draft);
});

it('previews a menu version in the real header without publishing it', function () {
    $draft = app(CreateDraft::class)->handle($this->menu, $this->author, ['items' => [['label' => 'Draft link', 'type' => 'page', 'page_id' => $this->about->id, 'url' => null]]]);

    actingAs($this->author)->get(RevisionPreviewController::url($draft))
        ->assertOk()
        ->assertSee('Preview: Main menu')
        ->assertSee('Draft link')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    get('/about')->assertDontSee('Draft link');
});
