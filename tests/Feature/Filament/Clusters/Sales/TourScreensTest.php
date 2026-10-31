<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Booking\TourStatus;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\EditProperty;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Filament\Clusters\Properties\Resources\Properties\RelationManagers\ClosuresRelationManager;
use App\Filament\Clusters\Properties\Resources\Properties\RelationManagers\TourHoursRelationManager;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ViewLead;
use App\Filament\Clusters\Sales\Resources\Leads\RelationManagers\ToursRelationManager;
use App\Filament\Clusters\Sales\Resources\Tours\Pages\ListTours;
use App\Filament\Clusters\Sales\Resources\Tours\Pages\ViewTour;
use App\Filament\Clusters\Sales\Resources\Tours\TourResource;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyClosure;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 04:30:00'); // Monday 1 March 2027, 10:00 in India
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->other = Property::query()->where('code', 'A-71')->sole();
    $this->sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Ravi Sales']);
    $this->sales->assignedProperties()->attach($this->property);
    $this->operations = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create(['name' => 'Ola Operations']);
    $this->lead = Lead::factory()->forProperty($this->property)->create(['submitted_name' => 'Asha Verma']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('lets the location\'s tour staff enter tour hours, and operations staff record closures, on the property page', function () {
    actingAs($this->sales);

    Livewire::test(TourHoursRelationManager::class, ['ownerRecord' => $this->property, 'pageClass' => EditProperty::class])
        ->assertSee('No tour hours')
        ->callAction(TestAction::make('addTourHours')->table(), ['weekday' => 2, 'start_time' => '10:00', 'end_time' => '11:00', 'slot_minutes' => 30, 'max_concurrent_tours' => 1])
        ->assertHasNoActionErrors()
        ->assertNotified('Tour hours added')
        ->callAction(TestAction::make('addTourHours')->table(), ['weekday' => 2, 'start_time' => '10:30', 'end_time' => '12:00', 'slot_minutes' => 30, 'max_concurrent_tours' => 1])
        ->assertNotified('Not saved')
        ->assertSee('Tuesday');

    expect(TourAvailabilityRule::query()->sole()->summary())->toBe('Tuesday, 10:00 to 11:00: 30-minute tours, up to 1 at a time');

    actingAs($this->operations);

    Livewire::test(TourHoursRelationManager::class, ['ownerRecord' => $this->property, 'pageClass' => EditProperty::class])
        ->assertActionHidden(TestAction::make('addTourHours')->table());
    Livewire::test(ClosuresRelationManager::class, ['ownerRecord' => $this->property, 'pageClass' => EditProperty::class])
        ->callAction(TestAction::make('addClosure')->table(), ['starts_at' => '2027-03-02 00:00', 'ends_at' => '2027-03-03 00:00', 'reason' => 'Public holiday'])
        ->assertHasNoActionErrors()
        ->assertSee('Public holiday');

    expect(PropertyClosure::query()->sole()->created_by)->toBe($this->operations->id)
        ->and(TourHoursRelationManager::canViewForRecord($this->property, EditProperty::class))->toBeTrue();

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create());

    expect(TourHoursRelationManager::canViewForRecord($this->property, EditProperty::class))->toBeFalse();
});

it('arranges a tour from the enquiry and works through it on the tour page (UJ-10)', function () {
    TourAvailabilityRule::factory()->for($this->property)->window(2, '10:00', '11:00', 30, 1)->create();
    actingAs($this->sales);

    Livewire::test(ToursRelationManager::class, ['ownerRecord' => $this->lead, 'pageClass' => ViewLead::class])
        ->assertSee('No tours')
        ->callAction(TestAction::make('arrangeTour')->table(), ['property_id' => $this->property->id, 'starts_at' => '2027-03-02T04:30:00+00:00', 'visitors' => 2])
        ->assertHasNoActionErrors()
        ->assertNotified('Tour arranged and confirmed');

    $tour = TourRequest::query()->sole();

    expect($tour->status)->toBe(TourStatus::Confirmed);

    Livewire::test(ViewTour::class, ['record' => $tour->getRouteKey()])
        ->assertSee(['Tuesday 2 March 2027, 10:00 to 10:30 (India time)', 'Asha Verma', 'Tour arranged and confirmed'])
        ->assertActionHidden('confirm')
        ->callAction('assignHost', ['host_user_id' => $this->sales->id])
        ->assertNotified('Host updated')
        ->callAction('reschedule', ['starts_at' => '2027-03-02T05:00:00+00:00'])
        ->assertNotified('Tour moved')
        ->callAction('cancel', ['reason' => 'Visitor asked to cancel'])
        ->assertNotified('Tour cancelled')
        ->assertActionHidden('reschedule');

    expect($tour->fresh())->status->toBe(TourStatus::Cancelled)->host_user_id->toBe($this->sales->id);
});

it('confirms a visitor\'s request, and refuses a change made on a stale screen', function () {
    TourAvailabilityRule::factory()->for($this->property)->window(2, '10:00', '11:00', 30, 2)->create();
    $request = TourRequest::factory()->forProperty($this->property)->create(['lead_id' => $this->lead->id, 'scheduled_start_at' => '2027-03-02 04:30:00']);
    actingAs($this->sales);
    $screen = Livewire::test(ViewTour::class, ['record' => $request->getRouteKey()])->assertSee('Requested: not yet confirmed');

    $request->forceFill(['status' => TourStatus::Confirmed, 'confirmed_at' => now()])->save();

    $screen->callAction('reschedule', ['starts_at' => '2027-03-02T05:00:00+00:00'])->assertNotified('Not saved');

    expect($request->fresh()->scheduled_start_at->equalTo(CarbonImmutable::parse('2027-03-02 04:30:00')))->toBeTrue();

    $second = TourRequest::factory()->forProperty($this->property)->create(['lead_id' => $this->lead->id, 'scheduled_start_at' => '2027-03-02 05:00:00']);

    Livewire::test(ViewTour::class, ['record' => $second->getRouteKey()])->callAction('confirm')->assertNotified('Tour confirmed');

    expect($second->fresh()->status)->toBe(TourStatus::Confirmed);
});

it('lists only the tours within each person\'s scope, with requests waiting counted', function () {
    $mine = TourRequest::factory()->forProperty($this->property)->create();
    $elsewhere = TourRequest::factory()->forProperty($this->other)->create();
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->other);

    actingAs($this->sales);
    Livewire::test(ListTours::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$elsewhere]);
    get(TourResource::getUrl('view', ['record' => $elsewhere]))->assertNotFound();
    expect(TourResource::getNavigationBadge())->toBe('1');

    actingAs($manager);
    Livewire::test(ListTours::class)->assertCanSeeTableRecords([$elsewhere])->assertCanNotSeeTableRecords([$mine]);

    actingAs($this->operations);
    Livewire::test(ListTours::class)->assertCanSeeTableRecords([$mine, $elsewhere]);
    Livewire::test(ViewTour::class, ['record' => $mine->getRouteKey()])->assertActionHidden('confirm')->assertActionHidden('cancel');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create());
    get(TourResource::getUrl('index'))->assertForbidden();
});

it('groups tours by day in India time and shows the tours the user hosts', function () {
    $early = TourRequest::factory()->forProperty($this->property)->create(['scheduled_start_at' => '2027-03-01 19:00:00']); // 2 March, 00:30 in India
    $hosted = TourRequest::factory()->forProperty($this->property)->confirmed()->create(['scheduled_start_at' => '2027-03-02 04:30:00', 'host_user_id' => $this->sales->id]);
    actingAs($this->sales);

    Livewire::test(ListTours::class)
        ->assertSee('Tuesday 2 March 2027')
        ->assertDontSee('Monday 1 March 2027')
        ->filterTable('hosting', true)
        ->assertCanSeeTableRecords([$hosted])
        ->assertCanNotSeeTableRecords([$early]);
});

it('keeps the back office and the website working without the tour tables, such as on the owner\'s server (OD-120)', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, TourRequest::TABLES, true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/`(tour_availability_rules|property_closures|tour_requests)`/', $query->sql) === 1) {
            $queries[] = $query->sql;
        }
    });
    $admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $listed = publishPropertyProfileForTest($this->property);

    actingAs($admin);
    get(PropertyResource::getUrl('edit', ['record' => $this->property]))->assertOk()->assertDontSee('No tour hours');
    get(LeadResource::getUrl('view', ['record' => $this->lead]))->assertOk();
    get(TourResource::getUrl('index'))->assertForbidden();
    auth()->logout();
    get('/book-a-tour')->assertOk()->assertSee('Tour times are not offered online yet');
    get($listed->path())->assertOk()->assertDontSee('Book a tour');

    expect($queries)->toBe([]);
});
