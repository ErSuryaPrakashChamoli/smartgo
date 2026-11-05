<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ListLeads;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ViewLead;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->otherProperty = Property::query()->where('code', 'H-105')->sole();
    $this->sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->property);
    $this->contact = Contact::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone_e164' => null, 'company_name' => null]);
    $this->lead = Lead::factory()->forProperty($this->property)->create([
        'contact_id' => $this->contact->id, 'submitted_name' => 'Asha Verma', 'submitted_email' => 'asha@example.com', 'submitted_phone' => '+919876543210',
    ]);
    $this->earlier = Lead::factory()->forProperty($this->property)->create([
        'contact_id' => $this->contact->id, 'submitted_name' => 'Asha Verma', 'submitted_email' => 'asha@example.com', 'created_at' => now()->subWeek(),
    ]);
    $this->elsewhere = Lead::factory()->forProperty($this->otherProperty)->create(['contact_id' => $this->contact->id]);
});

it('links the contact\'s other enquiries the user may see, and only counts the others', function () {
    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->lead]))
        ->assertOk()
        ->assertSee('Other enquiries from this contact')
        ->assertSee(LeadResource::getUrl('view', ['record' => $this->earlier]), false)
        ->assertSee($this->earlier->reference)
        ->assertDontSee($this->elsewhere->reference)
        ->assertSee('1 more about location you do not work on.');
});

it('shows details the contact lacks and another contact with the same phone', function () {
    Contact::factory()->create(['phone_e164' => '+919876543210']);

    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->lead]))
        ->assertOk()
        ->assertSee('Not on the contact record yet')
        ->assertSee('Phone: +919876543210 (the contact has none)')
        ->assertSee('Another contact has the same phone (0 enquiries)');
});

it('copies a chosen detail to the contact from the enquiry page, and then offers it no longer', function () {
    actingAs($this->sales);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->assertActionVisible('updateContact')
        ->callAction('updateContact', ['fields' => ['phone']])
        ->assertHasNoActionErrors()
        ->assertNotified('Contact updated')
        ->assertActionHidden('updateContact');

    expect($this->contact->fresh()->phone_e164)->toBe('+919876543210');
});

it('offers no contact update when the details match, or to read-only staff', function () {
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);

    actingAs($manager);
    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])->assertActionHidden('updateContact');

    actingAs($this->sales);
    Livewire::test(ViewLead::class, ['record' => $this->earlier->getRouteKey()])->assertActionHidden('updateContact');
});

it('marks a repeat contact in the inbox', function () {
    $single = Lead::factory()->forProperty($this->property)->create(['submitted_company' => 'Solo Ltd']);

    actingAs($this->sales);

    Livewire::test(ListLeads::class)
        ->assertCanSeeTableRecords([$this->lead, $this->earlier, $single])
        ->assertSee('Repeat contact: 2 other enquiries')
        ->assertSeeHtml('Solo Ltd');
});
