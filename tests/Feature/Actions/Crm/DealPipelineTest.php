<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\AssignDeal;
use App\Actions\Crm\LoseDeal;
use App\Actions\Crm\MoveDeal;
use App\Actions\Crm\OpenDeal;
use App\Actions\Crm\UpdateDeal;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\LeadStatus;
use App\Enums\Crm\PipelineOutcome;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->property);
    $this->lead = Lead::factory()->forProperty($this->property)->create();

    // Test stages only: the owner has not decided the names, order or probabilities (D-09, A-42).
    $this->first = PipelineStage::factory()->create(['name' => 'Stage one', 'sort_order' => 1]);
    $this->second = PipelineStage::factory()->create(['name' => 'Stage two', 'sort_order' => 2]);
    $this->lost = PipelineStage::factory()->lost()->create(['name' => 'Closed lost']);
});

/**
 * Another Sales/CRM Executive, who works on H-105 only.
 */
function salesElsewhere(): User
{
    $user = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $user->assignedProperties()->attach(Property::query()->where('code', 'H-105')->sole());

    return $user;
}

it('opens a deal from an enquiry in an open stage, with the values staff typed, owned by its opener and audited', function () {
    $deal = app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id, 'seats' => '12', 'monthly_value' => '125000.50', 'expected_close_on' => '2026-12-15']);

    $entry = Activity::query()->inLog('leads')->where('event', 'deal_opened')->sole();

    expect($deal->only(['lead_id', 'pipeline_stage_id', 'owner_user_id', 'created_by', 'seats', 'monthly_value_minor']))
        ->toBe(['lead_id' => $this->lead->id, 'pipeline_stage_id' => $this->first->id, 'owner_user_id' => $this->sales->id, 'created_by' => $this->sales->id, 'seats' => 12, 'monthly_value_minor' => 12500050])
        ->and($deal->expected_close_on->toDateString())->toBe('2026-12-15')
        ->and($deal->isOpen())->toBeTrue()
        ->and($entry->subject->is($deal))->toBeTrue()
        ->and($entry->causer->is($this->sales))->toBeTrue()
        ->and($entry->properties->all())->toEqual(['deal_id' => $deal->id, 'reference' => $this->lead->reference, 'stage' => 'Stage one']);
});

it('leaves every value empty when staff type none: nothing is filled in', function () {
    $deal = app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id]);

    expect($deal->fresh()->only(['seats', 'monthly_value_minor', 'expected_close_on']))->toBe(['seats' => null, 'monthly_value_minor' => null, 'expected_close_on' => null]);
});

it('opens no deal until the Super Admin has set up an open stage, and only in an active open one', function () {
    PipelineStage::query()->update(['is_active' => false]);

    expect(fn () => app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id]))
        ->toThrow(ValidationException::class, 'No open pipeline stages are set up yet');

    PipelineStage::query()->whereKey($this->second->id)->update(['is_active' => true]);

    expect(fn () => app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id]))->toThrow(ValidationException::class, 'Choose one of the open pipeline stages.')
        ->and(fn () => app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->lost->id]))->toThrow(ValidationException::class, 'Choose one of the open pipeline stages.')
        ->and(Deal::query()->count())->toBe(0);
});

it('keeps one open deal per enquiry, and allows another once it is closed', function () {
    $deal = app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id]);

    expect(fn () => app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->second->id]))
        ->toThrow(ValidationException::class, 'This enquiry already has an open deal (deal '.$deal->id.')');

    app(LoseDeal::class)->handle($this->sales, $deal, ['pipeline_stage_id' => $this->lost->id, 'reason' => 'Chose another provider']);

    expect(app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->second->id])->isOpen())->toBeTrue()
        ->and(Deal::query()->count())->toBe(2);
});

it('opens no deal on a lost or disqualified enquiry', function (LeadStatus $status) {
    $this->lead->forceFill(['status' => $status])->save();

    expect(fn () => app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id]))
        ->toThrow(ValidationException::class, 'Reopen it before opening a deal.');
})->with([LeadStatus::Lost, LeadStatus::Disqualified]);

it('refuses staff who may not work on the enquiry: another location\'s sales, the Property Manager, a deactivated account', function (string $who) {
    $actor = match ($who) {
        'sales elsewhere' => salesElsewhere(),
        'property manager' => tap(User::factory()->withRole(StaffRole::PropertyManager)->create(), fn (User $user) => $user->assignedProperties()->attach($this->property)),
        'deactivated' => tap($this->sales, fn (User $user) => $user->forceFill(['is_active' => false])->save()),
    };

    expect(fn () => app(OpenDeal::class)->handle($actor, $this->lead, ['pipeline_stage_id' => $this->first->id]))->toThrow(AuthorizationException::class)
        ->and(Deal::query()->count())->toBe(0);
})->with(['sales elsewhere', 'property manager', 'deactivated']);

it('checks the typed values', function (array $values, string $message) {
    expect(fn () => app(OpenDeal::class)->handle($this->sales, $this->lead, ['pipeline_stage_id' => $this->first->id, ...$values]))
        ->toThrow(ValidationException::class, $message);
})->with([
    'a value that is not rupees' => [['monthly_value' => '1,25,000'], 'Enter an amount in rupees'],
    'no seats' => [['seats' => '0'], 'The seats field must be between 1 and 2000.'],
    'not a date' => [['expected_close_on' => 'soon'], 'The expected close date field must be a valid date.'],
]);

it('moves an open deal between open stages, audited, and refuses a stale move, a closing stage or the same stage', function () {
    $deal = Deal::factory()->forLead($this->lead)->inStage($this->first)->create();

    app(MoveDeal::class)->handle($this->sales, $deal, $this->second->id, $this->first->id);

    expect($deal->fresh()->pipeline_stage_id)->toBe($this->second->id)
        ->and(Activity::query()->where('event', 'deal_moved')->sole()->properties->all())->toEqual(['deal_id' => $deal->id, 'from' => 'Stage one', 'to' => 'Stage two'])
        ->and(fn () => app(MoveDeal::class)->handle($this->sales, $deal, $this->first->id, $this->first->id))->toThrow(ValidationException::class, 'Someone else moved this deal.')
        ->and(fn () => app(MoveDeal::class)->handle($this->sales, $deal, $this->lost->id, $this->second->id))->toThrow(ValidationException::class, 'Choose another of the open pipeline stages.')
        ->and(fn () => app(MoveDeal::class)->handle($this->sales, $deal, $this->second->id, $this->second->id))->toThrow(ValidationException::class, 'Choose another of the open pipeline stages.')
        ->and(fn () => app(MoveDeal::class)->handle(salesElsewhere(), $deal, $this->first->id, $this->second->id))->toThrow(AuthorizationException::class)
        ->and($deal->fresh()->pipeline_stage_id)->toBe($this->second->id);
});

it('changes an open deal\'s values with each change audited, and nothing when nothing changed', function () {
    $deal = Deal::factory()->forLead($this->lead)->inStage($this->first)->create(['seats' => 10]);

    app(UpdateDeal::class)->handle($this->sales, $deal, ['seats' => 12, 'monthly_value' => '90000', 'expected_close_on' => null]);
    app(UpdateDeal::class)->handle($this->sales, $deal, ['seats' => 12, 'monthly_value' => '90000']);

    expect($deal->fresh()->only(['seats', 'monthly_value_minor']))->toBe(['seats' => 12, 'monthly_value_minor' => 9000000])
        ->and(Activity::query()->where('event', 'deal_updated')->sole()->properties['changes'])
        ->toEqual(['seats' => ['from' => 10, 'to' => 12], 'monthly_value_minor' => ['from' => null, 'to' => 9000000]]);
});

it('gives an open deal another owner who may work on its enquiry, with the enquiry\'s assignment permission', function () {
    $deal = Deal::factory()->forLead($this->lead)->inStage($this->first)->create(['owner_user_id' => $this->sales->id]);
    $colleague = tap(User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(), fn (User $user) => $user->assignedProperties()->attach($this->property));
    $manager = tap(User::factory()->withRole(StaffRole::PropertyManager)->create(), fn (User $user) => $user->assignedProperties()->attach($this->property));

    app(AssignDeal::class)->handle($this->sales, $deal, $colleague, $this->sales->id);

    expect($deal->fresh()->owner_user_id)->toBe($colleague->id)
        ->and(Activity::query()->where('event', 'deal_assigned')->sole()->properties->all())->toEqual(['deal_id' => $deal->id, 'from_user_id' => $this->sales->id, 'to_user_id' => $colleague->id])
        ->and(fn () => app(AssignDeal::class)->handle($this->sales, $deal, $this->sales, $this->sales->id))->toThrow(ValidationException::class, 'Someone else changed who owns this deal.')
        ->and(fn () => app(AssignDeal::class)->handle($this->sales, $deal, salesElsewhere(), $colleague->id))->toThrow(ValidationException::class, 'This person cannot work on this deal\'s enquiry.')
        ->and(fn () => app(AssignDeal::class)->handle($this->sales, $deal, $manager, $colleague->id))->toThrow(ValidationException::class, 'This person cannot work on this deal\'s enquiry.')
        ->and(fn () => app(AssignDeal::class)->handle($manager, $deal, $this->sales, $colleague->id))->toThrow(AuthorizationException::class)
        ->and($deal->fresh()->owner_user_id)->toBe($colleague->id);
});

it('marks a deal lost once, in a lost stage, with the reason given and audited', function () {
    $deal = Deal::factory()->forLead($this->lead)->inStage($this->first)->create();

    expect(fn () => app(LoseDeal::class)->handle($this->sales, $deal, ['pipeline_stage_id' => $this->lost->id, 'reason' => '']))->toThrow(ValidationException::class, 'The reason field is required.')
        ->and(fn () => app(LoseDeal::class)->handle($this->sales, $deal, ['pipeline_stage_id' => $this->second->id, 'reason' => 'Budget']))->toThrow(ValidationException::class, 'Choose one of the lost stages.');

    app(LoseDeal::class)->handle($this->sales, $deal, ['pipeline_stage_id' => $this->lost->id, 'reason' => 'Chose another provider']);

    expect($deal->fresh()->outcome())->toBe(PipelineOutcome::Lost)
        ->and($deal->fresh()->only(['pipeline_stage_id', 'lost_reason']))->toBe(['pipeline_stage_id' => $this->lost->id, 'lost_reason' => 'Chose another provider'])
        ->and(Activity::query()->where('event', 'deal_lost')->sole()->properties->all())->toEqual(['deal_id' => $deal->id, 'stage' => 'Closed lost', 'reason' => 'Chose another provider'])
        ->and(fn () => app(LoseDeal::class)->handle($this->sales, $deal, ['pipeline_stage_id' => $this->lost->id, 'reason' => 'Again']))->toThrow(ValidationException::class, 'This deal is already lost.')
        ->and(fn () => app(MoveDeal::class)->handle($this->sales, $deal, $this->second->id, $this->lost->id))->toThrow(ValidationException::class, 'so it stays where it closed')
        ->and(fn () => app(UpdateDeal::class)->handle($this->sales, $deal, ['seats' => 3]))->toThrow(ValidationException::class, 'so its details stay as they were');
});

it('says when no lost stage is set up yet', function () {
    $this->lost->update(['is_active' => false]);
    $deal = Deal::factory()->forLead($this->lead)->inStage($this->first)->create();

    expect(fn () => app(LoseDeal::class)->handle($this->sales, $deal, ['pipeline_stage_id' => $this->lost->id, 'reason' => 'Budget']))
        ->toThrow(ValidationException::class, 'No lost stage is set up yet');
});

it('shows each member of staff only the deals of enquiries they may see', function () {
    $mine = Deal::factory()->forLead($this->lead)->create();
    $elsewhere = Deal::factory()->forLead(Lead::factory()->forProperty(Property::query()->where('code', 'H-105')->sole())->create())->create();
    $general = Deal::factory()->forLead(Lead::factory()->create())->create();
    $admin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();

    expect(Deal::query()->visibleTo($this->sales)->pluck('id')->all())->toBe([$mine->id])
        ->and(Deal::query()->visibleTo($admin)->orderBy('id')->pluck('id')->all())->toBe([$mine->id, $elsewhere->id, $general->id])
        ->and(Deal::query()->visibleTo($marketing)->count())->toBe(0);
});

it('keeps a stage\'s outcome once a deal has used it', function () {
    Deal::factory()->inStage($this->first)->create();

    expect(fn () => $this->first->update(['outcome' => PipelineOutcome::Won]))->toThrow(ValidationException::class, 'Deals already use this stage')
        ->and($this->first->fresh()->outcome)->toBe(PipelineOutcome::Open)
        ->and($this->second->update(['outcome' => PipelineOutcome::Won]))->toBeTrue();
});
