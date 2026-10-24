<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\DesignPreviews\Pages\BookingRequests;
use App\Filament\Clusters\DesignPreviews\Pages\BuildingsAndFloors;
use App\Filament\Clusters\DesignPreviews\Pages\BusinessDetails;
use App\Filament\Clusters\DesignPreviews\Pages\Cities;
use App\Filament\Clusters\DesignPreviews\Pages\DashboardOverview;
use App\Filament\Clusters\DesignPreviews\Pages\EnquiryManagement;
use App\Filament\Clusters\DesignPreviews\Pages\MixedZones;
use App\Filament\Clusters\DesignPreviews\Pages\PermissionsReview;
use App\Filament\Clusters\DesignPreviews\Pages\PooledInventory;
use App\Filament\Clusters\DesignPreviews\Pages\PricingConfiguration;
use App\Filament\Clusters\DesignPreviews\Pages\PropertyEditor;
use App\Filament\Clusters\DesignPreviews\Pages\SeatInventory;
use App\Filament\Clusters\DesignPreviews\Pages\TourManagement;
use App\Filament\Clusters\DesignPreviews\Pages\WebsiteContent;
use App\Filament\Clusters\DesignPreviews\Pages\WorkspaceProducts;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

dataset('design preview pages', [
    'dashboard overview' => [DashboardOverview::class],
    'property editor' => [PropertyEditor::class],
    'buildings and floors' => [BuildingsAndFloors::class],
    'workspace products' => [WorkspaceProducts::class],
    'individual seats' => [SeatInventory::class],
    'pooled capacity' => [PooledInventory::class],
    'mixed zones' => [MixedZones::class],
    'cities' => [Cities::class],
    'pricing' => [PricingConfiguration::class],
    'enquiries' => [EnquiryManagement::class],
    'booking requests' => [BookingRequests::class],
    'tours' => [TourManagement::class],
    'contact and legal details' => [BusinessDetails::class],
    'website content' => [WebsiteContent::class],
    'permissions review' => [PermissionsReview::class],
]);

it('renders for the Super Admin', function (string $page) {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test($page)->assertOk()->assertSee('Design preview');
})->with('design preview pages');

it('returns 403 to other staff roles', function (string $page) {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create());

    $this->get($page::getUrl())->assertForbidden();
})->with('design preview pages');

it('is not available in production, even to the Super Admin', function (string $page) {
    app()->detectEnvironment(fn (): string => 'production');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());

    $this->get($page::getUrl())->assertForbidden();
})->with('design preview pages');

it('never saves anything when a preview form is submitted', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(DashboardOverview::class)
        ->call('save')
        ->assertNotified('Design preview: nothing was saved');
});

it('refuses instant online booking for a category until the payment decisions are made', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(WorkspaceProducts::class)
        ->fillForm(['acquisition_workflow' => 'instant_booking'])
        ->call('save')
        ->assertHasFormErrors(['acquisition_workflow'])
        ->assertNotNotified();
});

it('validates a preview form before reporting that nothing was saved', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(BuildingsAndFloors::class)
        ->fillForm(['property' => null])
        ->call('save')
        ->assertHasFormErrors(['property' => 'required']);
});

describe('pricing', function () {
    beforeEach(fn () => actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create()));

    it('never stores a missing price as zero', function () {
        Livewire::test(PricingConfiguration::class)
            ->fillForm(['pricing_type' => 'fixed', 'amount' => '0', 'billing_period' => 'hour', 'unit' => 'room', 'is_free' => false])
            ->call('save')
            ->assertHasFormErrors(['amount'])
            ->assertSee('A missing price is never stored as ₹0.');
    });

    it('accepts zero only when the product is marked free of charge', function () {
        Livewire::test(PricingConfiguration::class)
            ->fillForm(['pricing_type' => 'fixed', 'amount' => '0', 'billing_period' => 'hour', 'unit' => 'room', 'is_free' => true])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Design preview: nothing was saved');
    });

    it('requires an amount for starting and fixed prices but not for price on request', function () {
        Livewire::test(PricingConfiguration::class)
            ->fillForm(['pricing_type' => 'starting', 'amount' => null])
            ->call('save')
            ->assertHasFormErrors(['amount' => 'required']);

        Livewire::test(PricingConfiguration::class)
            ->fillForm(['pricing_type' => 'on_request', 'amount' => null])
            ->call('save')
            ->assertHasNoFormErrors();
    });
});

it('refuses to approve a booking request whose space is no longer free', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(BookingRequests::class)
        ->callAction(TestAction::make('approve')->table(2))
        ->assertNotified('Not approved: the space is no longer free');
});

it('only offers approval for requests that are still waiting for review', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(BookingRequests::class)
        ->assertActionVisible(TestAction::make('approve')->table(1))
        ->assertActionHidden(TestAction::make('approve')->table(4));
});

it('sends no message when a tour is confirmed or an enquiry is followed up', function () {
    Mail::fake();
    Notification::fake();
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(TourManagement::class)
        ->callAction(TestAction::make('confirm')->table(1), ['confirmed_at' => now()->addDay()->setTime(11, 0), 'host' => 'Sample host 1'])
        ->assertNotified('Design preview: nothing was saved');

    Livewire::test(EnquiryManagement::class)
        ->callAction(TestAction::make('logFollowUp')->table(1), ['type' => 'call', 'summary' => 'Sample follow-up'])
        ->assertNotified('Design preview: nothing was saved');

    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

describe('contact and legal details', function () {
    beforeEach(fn () => actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create()));

    it('rejects a GSTIN in the wrong format', function () {
        Livewire::test(BusinessDetails::class)
            ->fillForm(['gstin' => 'NOT-A-GSTIN'])
            ->call('save')
            ->assertHasFormErrors(['gstin' => 'regex']);
    });

    it('accepts a correctly formatted GSTIN', function () {
        Livewire::test(BusinessDetails::class)
            ->fillForm(['gstin' => '09ABCDE1234F1Z5'])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('marks every contact and legal field as missing and keeps the brand fixed', function () {
        Livewire::test(BusinessDetails::class)
            ->assertSee('Missing: to be supplied by SmartGro.')
            ->assertFormFieldDisabled('brand_name')
            ->assertSchemaStateSet(['brand_name' => 'SmartGro', 'legal_name' => null, 'gstin' => null]);
    });
});

describe('website content', function () {
    beforeEach(fn () => actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create()));

    it('does not let authors approve or publish their own changes', function () {
        Livewire::test(WebsiteContent::class)
            ->assertActionDisabled('approve')
            ->assertActionDisabled('publish');
    });

    it('refuses media that is not confirmed as new, original material', function () {
        Livewire::test(WebsiteContent::class)
            ->fillForm(['blocks' => [['type' => 'media', 'data' => ['alt_text' => 'Sample', 'origin' => 'commissioned', 'not_from_old_website' => false]]]])
            ->call('save')
            ->assertHasErrors()
            ->assertNotNotified();
    });
});

it('lists as implemented exactly the capabilities that exist in code', function () {
    $implemented = collect(PermissionsReview::catalogue())->where('basis', 'implemented')->pluck('capability')->sort()->values()->all();

    expect($implemented)->toBe(collect(Capability::cases())->map(fn (Capability $capability): string => $capability->value)->sort()->values()->all());
});

it('never writes to the database from a preview screen', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    Livewire::test(BookingRequests::class)->callAction(TestAction::make('approve')->table(1));
    Livewire::test(TourManagement::class)->callAction(TestAction::make('cancel')->table(1), ['reason' => 'Sample']);
    Livewire::test(PricingConfiguration::class)->fillForm(['pricing_type' => 'on_request'])->call('save');
    Livewire::test(BusinessDetails::class)->fillForm(['legal_name' => 'Sample entity'])->call('save');
    Livewire::test(Cities::class)->callAction(TestAction::make('toggleVisibility')->table('gurugram'));

    expect($writes)->toBeEmpty();
});
