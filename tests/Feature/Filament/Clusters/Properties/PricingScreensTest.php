<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Pricing\ChangePricePlanState;
use App\Actions\Pricing\SavePricePlan;
use App\Enums\Access\StaffRole;
use App\Enums\Pricing\OfferStatus;
use App\Enums\Pricing\PricePlanStatus;
use App\Filament\Clusters\Properties\Resources\Offers\OfferResource;
use App\Filament\Clusters\Properties\Resources\Offers\Pages\CreateOffer;
use App\Filament\Clusters\Properties\Resources\Offers\Pages\ViewOffer;
use App\Filament\Clusters\Properties\Resources\PricePlans\Pages\CreatePricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\Pages\ViewPricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\PricePlanResource;
use App\Models\Offer;
use App\Models\PricePlan;
use App\Models\Property;
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
    $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00:00'));
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->rooms = offerForTest($this->property, 'meeting_room');
    $this->finance = User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create();
});

it('lets Finance enter a price through the form, and only someone else activate it', function () {
    actingAs($this->finance);

    Livewire::test(CreatePricePlan::class)
        ->fillForm(['property_id' => $this->property->id, 'offering_id' => $this->rooms->id, 'unit' => 'per_hour', 'amount' => '1500', 'valid_from' => '2027-03-01'])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = PricePlan::query()->sole();

    Livewire::test(ViewPricePlan::class, ['record' => $plan->getRouteKey()])
        ->assertSee(['Meeting rooms at SmartGro A-70', '₹1,500 + GST', 'Price plan entered'])
        ->callAction(TestAction::make('submit'))
        ->assertActionHidden(TestAction::make('activate'));

    actingAs($this->approver);
    Livewire::test(ViewPricePlan::class, ['record' => $plan->getRouteKey()])
        ->callAction(TestAction::make('activate'))
        ->assertNotified('Price plan activated')
        ->assertSee(['Current', $this->approver->name]);

    expect($plan->fresh()->status)->toBe(PricePlanStatus::Active);
});

it('shows why an activation is refused and keeps the plan waiting', function () {
    $save = fn (string $amount): PricePlan => app(ChangePricePlanState::class)->handle($this->finance, app(SavePricePlan::class)->handle($this->finance, null, ['offering_id' => $this->rooms->id, 'unit' => 'per_hour', 'amount' => $amount, 'valid_from' => '2027-03-01']), 'submit');
    $first = $save('1500');
    $second = $save('1600');
    app(ChangePricePlanState::class)->handle($this->approver, $first, 'activate');
    actingAs($this->approver);

    Livewire::test(ViewPricePlan::class, ['record' => $second->getRouteKey()])
        ->callAction(TestAction::make('activate'))
        ->assertNotified('Not saved');

    expect($second->fresh()->status)->toBe(PricePlanStatus::PendingApproval);
});

it('shows prices to the roles that see offerings, enters them only for Finance and the Super Admin, and limits Property Managers to their properties', function () {
    $other = offerForTest(Property::query()->where('code', 'A-71')->sole(), 'meeting_room');
    app(SavePricePlan::class)->handle($this->finance, null, ['offering_id' => $this->rooms->id, 'unit' => 'per_hour', 'amount' => '1500', 'valid_from' => '2027-03-01']);
    app(SavePricePlan::class)->handle($this->finance, null, ['offering_id' => $other->id, 'unit' => 'per_day', 'amount' => '9000', 'valid_from' => '2027-03-01']);
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();

    actingAs($manager)->get(PricePlanResource::getUrl('index'))->assertOk()->assertSee('₹1,500')->assertDontSee('₹9,000');
    actingAs($marketing)->get(PricePlanResource::getUrl('index'))->assertOk()->assertSee('₹9,000');
    actingAs($marketing)->get(PricePlanResource::getUrl('create'))->assertForbidden();
    actingAs($marketing)->get(OfferResource::getUrl('create'))->assertOk();
    actingAs($this->finance)->get(PricePlanResource::getUrl('create'))->assertOk();
});

it('drafts an offer in India Standard Time and lets only someone else who may approve prices approve it', function () {
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    actingAs($marketing);

    Livewire::test(CreateOffer::class)
        ->fillForm(['name' => 'Spring rooms', 'discount_type' => 'fixed', 'amount' => '500', 'starts_at' => '2027-03-02 09:00', 'ends_at' => '2027-03-31 18:00', 'terms' => 'Weekdays only.', 'offering_ids' => [(string) $this->rooms->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $offer = Offer::query()->sole();

    Livewire::test(ViewOffer::class, ['record' => $offer->getRouteKey()])
        ->assertSee(['₹500 off', '2 Mar 2027, 09:00 to 31 Mar 2027, 18:00 IST'])
        ->callAction(TestAction::make('submit'))
        ->assertActionHidden(TestAction::make('approve'));

    actingAs($this->approver);
    Livewire::test(ViewOffer::class, ['record' => $offer->getRouteKey()])->callAction(TestAction::make('approve'))->assertNotified('Offer approved');

    expect($offer->fresh()->status)->toBe(OfferStatus::Active);
});

it('keeps the back office working without pricing tables, such as on the owner\'s server (OD-120)', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, ['price_plans', 'offers', 'offer_offering'], true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/`(price_plans|offers|offer_offering)`/', $query->sql) === 1) {
            $queries[] = $query->sql;
        }
    });
    actingAs($this->finance);

    get(PricePlanResource::getUrl('index'))->assertForbidden();
    get(OfferResource::getUrl('index'))->assertForbidden();
    get('/admin')->assertOk();

    expect($queries)->toBe([]);
});
