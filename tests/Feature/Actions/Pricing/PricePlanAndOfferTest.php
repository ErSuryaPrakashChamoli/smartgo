<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Inventory\RecordAllocation;
use App\Actions\Pricing\ChangeOfferState;
use App\Actions\Pricing\ChangePricePlanState;
use App\Actions\Pricing\SaveOffer;
use App\Actions\Pricing\SavePricePlan;
use App\Enums\Access\StaffRole;
use App\Enums\Pricing\OfferStatus;
use App\Enums\Pricing\PricePlanStatus;
use App\Enums\Pricing\PriceUnit;
use App\Events\Pricing\PricePlanActivated;
use App\Models\Floor;
use App\Models\Offer;
use App\Models\PricePlan;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Pricing\PriceLookup;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00:00'));
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->rooms = offerForTest($this->property, 'meeting_room');
    $this->finance = User::factory()->withRole(StaffRole::Finance)->create();
    $this->secondFinance = User::factory()->withRole(StaffRole::Finance)->create();
    $this->admin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * @param  array<string, mixed>  $data
 */
function planForTest(User $user, int $offeringId, array $data = []): PricePlan
{
    return app(SavePricePlan::class)->handle($user, null, ['offering_id' => $offeringId, 'unit' => 'per_hour', 'amount' => '1500', 'valid_from' => '2027-03-01', ...$data]);
}

function activatedForTest(PricePlan $plan, User $author, User $approver): PricePlan
{
    return app(ChangePricePlanState::class)->handle($approver, app(ChangePricePlanState::class)->handle($author, $plan, 'submit'), 'activate');
}

function pricingErrorForTest(Closure $attempt): ?string
{
    try {
        $attempt();
    } catch (ValidationException $exception) {
        return collect($exception->errors())->flatten()->first();
    } catch (AuthorizationException $exception) {
        return 'Not allowed: '.$exception->getMessage();
    }

    return null;
}

it('stores what Finance enters in paise, tax-exclusive in INR, and audits it', function () {
    $plan = planForTest($this->finance, $this->rooms->id, ['amount' => '1500.50', 'setup_fee' => '2000', 'deposit_type' => 'months', 'deposit_months' => '2', 'min_term_months' => 3]);

    expect($plan->amount_minor)->toBe(150050)
        ->and($plan->setup_fee_minor)->toBe(200000)
        ->and($plan->deposit_months)->toBe('2.00')
        ->and([$plan->currency, $plan->tax_treatment, $plan->status])->toBe(['INR', 'exclusive', PricePlanStatus::Draft])
        ->and(PricePlan::format($plan->amount_minor))->toBe('₹1,500.50')
        ->and(Activity::query()->where('event', 'price_plan_created')->sole()->causer_id)->toBe($this->finance->id);
});

it('refuses units a category is not priced in, managed offices outside quotations, zero or malformed amounts and impossible dates', function () {
    $managed = offerForTest($this->property, 'managed_office');

    expect(pricingErrorForTest(fn () => planForTest($this->finance, $this->rooms->id, ['unit' => 'per_cabin_month'])))->toBe('Choose a unit Meeting rooms can be priced in.')
        ->and(pricingErrorForTest(fn () => planForTest($this->finance, $managed->id, ['unit' => 'per_space_month'])))->toBe('Managed offices are priced by quotation, not by a price plan.')
        ->and(pricingErrorForTest(fn () => planForTest($this->finance, $this->rooms->id, ['amount' => '0'])))->toBe('Enter the price; it cannot be zero.')
        ->and(pricingErrorForTest(fn () => planForTest($this->finance, $this->rooms->id, ['amount' => '1,500'])))->toBe('Enter an amount in rupees, such as 12500 or 12500.50.')
        ->and(pricingErrorForTest(fn () => planForTest($this->finance, $this->rooms->id, ['valid_from' => '2027-04-01', 'valid_to' => '2027-03-31'])))->toBe('The plan cannot end before it starts.')
        ->and(pricingErrorForTest(fn () => planForTest($this->finance, $this->rooms->id, ['deposit_type' => 'fixed'])))->toBe('Enter the deposit amount.')
        ->and(pricingErrorForTest(fn () => planForTest($this->finance, offerForTest($this->property, 'hot_desk')->id, ['unit' => 'bundle_days'])))->toBe('Enter how many days the bundle holds.')
        ->and(PricePlan::query()->count())->toBe(0);
});

it('prices a space on its own only when it is sold as that category at that property', function () {
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $room = Space::factory()->onFloor($floor)->create();

    expect(pricingErrorForTest(fn () => planForTest($this->finance, $this->rooms->id, ['space_id' => $room->id])))->toBe('Choose a space that is sold as this category at this property.');

    $room->offerings()->sync([$this->rooms->id]);

    expect(planForTest($this->finance, $this->rooms->id, ['space_id' => $room->id])->space_id)->toBe($room->id);
});

it('keeps entering a price and activating it with different people (BR-PR-2)', function () {
    $plan = planForTest($this->finance, $this->rooms->id);
    $editor = User::factory()->withRole(StaffRole::Finance)->create();
    app(SavePricePlan::class)->handle($editor, $plan, ['offering_id' => $this->rooms->id, 'unit' => 'per_hour', 'amount' => '1600', 'valid_from' => '2027-03-01']);
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $operations = User::factory()->withRole(StaffRole::OperationsManager)->create();

    expect(pricingErrorForTest(fn () => planForTest($marketing, $this->rooms->id)))->toBe('Not allowed: Only Finance and the Super Admin enter prices.')
        ->and(pricingErrorForTest(fn () => planForTest($operations, $this->rooms->id)))->toStartWith('Not allowed');

    app(ChangePricePlanState::class)->handle($this->finance, $plan->fresh(), 'submit');

    foreach ([$this->finance, $editor, $operations] as $refused) {
        expect(pricingErrorForTest(fn () => app(ChangePricePlanState::class)->handle($refused, $plan->fresh(), 'activate')))->toStartWith('Not allowed');
    }

    Event::fake([PricePlanActivated::class]);
    $active = app(ChangePricePlanState::class)->handle($this->secondFinance, $plan->fresh(), 'activate');

    expect($active->status)->toBe(PricePlanStatus::Active)
        ->and($active->approved_by)->toBe($this->secondFinance->id)
        ->and($active->amount_minor)->toBe(160000)
        ->and(pricingErrorForTest(fn () => app(SavePricePlan::class)->handle($this->finance, $active, ['offering_id' => $this->rooms->id, 'unit' => 'per_hour', 'amount' => '1', 'valid_from' => '2027-03-01'])))
        ->toBe('Only a draft can be changed. An active plan stays as it was approved: enter the new price as a new plan.')
        ->and(Activity::query()->where('subject_id', $plan->id)->where('log_name', 'pricing')->orderBy('id')->pluck('event')->all())
        ->toBe(['price_plan_created', 'price_plan_changed', 'price_plan_submitted', 'price_plan_activated']);
    Event::assertDispatched(PricePlanActivated::class);
});

it('allows one active plan per category, space and unit on any day, and refuses a start before today', function () {
    $current = activatedForTest(planForTest($this->finance, $this->rooms->id, ['valid_from' => '2027-03-01']), $this->finance, $this->secondFinance);
    $next = planForTest($this->finance, $this->rooms->id, ['amount' => '1800', 'valid_from' => '2027-06-01']);
    app(ChangePricePlanState::class)->handle($this->finance, $next, 'submit');

    expect(pricingErrorForTest(fn () => app(ChangePricePlanState::class)->handle($this->secondFinance, $next->fresh(), 'activate')))
        ->toBe('Another active plan for the same category, space and unit covers some of these dates: plan '.$current->id.', ₹1,500 from 1 Mar 2027 to open-ended. Only one can apply on any day: Finance ends that plan first, or the dates change.')
        ->and(activatedForTest(planForTest($this->finance, $this->rooms->id, ['unit' => 'per_day', 'amount' => '9000']), $this->finance, $this->secondFinance)->status)->toBe(PricePlanStatus::Active);

    app(ChangePricePlanState::class)->handle($this->finance, $current, 'end', 'New rates from June', '2027-05-31');
    app(ChangePricePlanState::class)->handle($this->secondFinance, $next->fresh(), 'activate');

    $lookup = app(PriceLookup::class);

    expect($lookup->planFor($this->rooms, null, PriceUnit::PerHour, CarbonImmutable::parse('2027-05-31'))?->amount_minor)->toBe(150000)
        ->and($lookup->planFor($this->rooms, null, PriceUnit::PerHour, CarbonImmutable::parse('2027-06-01'))?->amount_minor)->toBe(180000)
        ->and($next->fresh()->stateOn(BusinessDay::today()))->toBe('Scheduled');

    $late = planForTest($this->finance, $this->rooms->id, ['unit' => 'per_half_day', 'valid_from' => '2027-02-15']);
    app(ChangePricePlanState::class)->handle($this->finance, $late, 'submit');

    expect(pricingErrorForTest(fn () => app(ChangePricePlanState::class)->handle($this->secondFinance, $late->fresh(), 'activate')))->toStartWith('This plan starts on 15 Feb 2027, before today.');
});

it('prefers a space\'s own price over the category\'s, and stops applying a retired plan at once', function () {
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $room = Space::factory()->onFloor($floor)->create();
    $other = Space::factory()->onFloor($floor)->create();
    $room->offerings()->sync([$this->rooms->id]);
    activatedForTest(planForTest($this->finance, $this->rooms->id), $this->finance, $this->secondFinance);
    $own = activatedForTest(planForTest($this->finance, $this->rooms->id, ['space_id' => $room->id, 'amount' => '2500']), $this->finance, $this->secondFinance);
    $lookup = app(PriceLookup::class);
    $today = BusinessDay::today();

    expect($lookup->planFor($this->rooms, $room, PriceUnit::PerHour, $today)?->amount_minor)->toBe(250000)
        ->and($lookup->planFor($this->rooms, $other, PriceUnit::PerHour, $today)?->amount_minor)->toBe(150000)
        ->and(pricingErrorForTest(fn () => app(ChangePricePlanState::class)->handle($this->finance, $own, 'retire', '')))->toBe('Give the reason in plain text, up to 500 characters.');

    app(ChangePricePlanState::class)->handle($this->finance, $own, 'retire', 'Room refitted');

    expect($lookup->planFor($this->rooms, $room, PriceUnit::PerHour, $today)?->amount_minor)->toBe(150000)
        ->and(Activity::query()->where('event', 'price_plan_retired')->sole()->properties['reason'])->toBe('Room refitted');
});

it('returns a submitted plan with the approver\'s reason, or lets its author withdraw it', function () {
    $plan = app(ChangePricePlanState::class)->handle($this->finance, planForTest($this->finance, $this->rooms->id), 'submit');
    $returned = app(ChangePricePlanState::class)->handle($this->admin, $plan, 'return', 'Check the hourly rate with the owner');

    expect($returned->status)->toBe(PricePlanStatus::Draft)
        ->and(Activity::query()->where('event', 'price_plan_returned')->sole()->properties['reason'])->toBe('Check the hourly rate with the owner');

    app(ChangePricePlanState::class)->handle($this->finance, $returned, 'submit');

    expect(app(ChangePricePlanState::class)->handle($this->finance, $returned->fresh(), 'return', 'Wrong unit')->status)->toBe(PricePlanStatus::Draft)
        ->and(Activity::query()->where('event', 'price_plan_withdrawn')->count())->toBe(1);
});

it('drafts offers with their terms, approved only by someone else who may approve prices, and expires them on their own (FR-INV-5)', function () {
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $data = ['name' => 'Spring rooms', 'code' => 'spring-27', 'discount_type' => 'percent', 'percent' => '12.5', 'starts_at' => '2027-03-02 09:00', 'ends_at' => '2027-03-31 23:59', 'terms' => 'Weekdays only.', 'offering_ids' => [$this->rooms->id]];

    expect(pricingErrorForTest(fn () => app(SaveOffer::class)->handle($marketing, null, [...$data, 'terms' => ''])))->toBe('Every offer needs its terms (BR-PR-5).')
        ->and(pricingErrorForTest(fn () => app(SaveOffer::class)->handle($marketing, null, [...$data, 'percent' => '120'])))->toBe('The percent field must not be greater than 100.')
        ->and(pricingErrorForTest(fn () => app(SaveOffer::class)->handle($marketing, null, [...$data, 'offering_ids' => []])))->toBe('Choose at least one workspace category at a property the offer applies to.')
        ->and(pricingErrorForTest(fn () => app(SaveOffer::class)->handle($marketing, null, [...$data, 'ends_at' => '2027-03-01 08:00'])))->toBe('The offer must end after it starts.')
        ->and(pricingErrorForTest(fn () => app(SaveOffer::class)->handle(User::factory()->withRole(StaffRole::OperationsManager)->create(), null, $data)))->toStartWith('Not allowed');

    $offer = app(SaveOffer::class)->handle($marketing, null, $data);
    app(ChangeOfferState::class)->handle($marketing, $offer, 'submit');

    expect($offer->fresh()->code)->toBe('SPRING-27')
        ->and($offer->fresh()->discountSummary())->toBe('12.5% off')
        ->and($offer->fresh()->starts_at->toIso8601String())->toBe('2027-03-02T03:30:00+00:00')
        ->and(pricingErrorForTest(fn () => app(ChangeOfferState::class)->handle($marketing, $offer, 'approve')))->toStartWith('Not allowed');

    app(ChangeOfferState::class)->handle($this->finance, $offer, 'approve');

    expect(Offer::query()->applyingAt(now())->count())->toBe(0)
        ->and(Offer::query()->applyingAt(now()->addDays(5))->count())->toBe(1)
        ->and($offer->fresh()->stateAt(now()->addMonth()))->toBe(OfferStatus::Expired)
        ->and(Offer::query()->applyingAt(now()->addMonth())->count())->toBe(0);

    $this->travel(5)->days();
    app(ChangeOfferState::class)->handle($this->finance, $offer->fresh(), 'end', 'Rooms fully booked');

    expect($offer->fresh()->stateAt(now()->addMinute()))->toBe(OfferStatus::Expired)
        ->and(Activity::query()->where('subject_type', Offer::class)->orderBy('id')->pluck('event')->all())->toBe(['offer_created', 'offer_submitted', 'offer_approved', 'offer_ended']);
});

it('takes "today" as the date in India, not UTC, for inventory and prices', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-01 20:00:00'));
    $operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $cabin = Space::factory()->onFloor(Floor::factory()->forProperty($this->property)->create(['building_level' => 1]))->create();

    expect(BusinessDay::today()->toDateString())->toBe('2027-03-02')
        ->and(pricingErrorForTest(fn () => app(RecordAllocation::class)->handle($operations, $cabin, ['agreement_reference' => 'AG-1', 'starts_on' => '2027-02-01', 'ends_on' => '2027-03-01', 'reason' => 'Signed'])))
        ->toBe('The end date cannot be before the start date or in the past.');
});
