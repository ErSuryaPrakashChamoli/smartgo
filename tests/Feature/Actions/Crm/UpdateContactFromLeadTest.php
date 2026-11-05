<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\UpdateContactFromLead;
use App\Enums\Access\StaffRole;
use App\Models\Contact;
use App\Models\Lead;
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
    $this->contact = Contact::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone_e164' => null, 'company_name' => 'Old Co']);
    $this->lead = Lead::factory()->forProperty($this->property)->create([
        'contact_id' => $this->contact->id,
        'submitted_name' => 'Asha Verma',
        'submitted_email' => 'asha@example.com',
        'submitted_phone' => '+919876543210',
        'submitted_company' => 'New Co',
    ]);
});

it('lists only the details typed on an enquiry that the contact lacks or has differently', function () {
    expect($this->lead->contactDifferences())->toBe([
        'phone' => ['label' => 'Phone', 'submitted' => '+919876543210', 'contact' => null],
        'company' => ['label' => 'Company', 'submitted' => 'New Co', 'contact' => 'Old Co'],
    ]);
});

it('copies only the chosen details to the contact, and audits which fields without their values', function () {
    app(UpdateContactFromLead::class)->handle($this->lead, $this->sales, ['phone']);

    $entry = Activity::query()->inLog('leads')->where('event', 'contact_updated')->sole();

    expect($this->contact->fresh()->only(['name', 'email', 'phone_e164', 'company_name']))
        ->toBe(['name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone_e164' => '+919876543210', 'company_name' => 'Old Co'])
        ->and($entry->causer->is($this->sales))->toBeTrue()
        ->and($entry->subject->is($this->lead))->toBeTrue()
        ->and($entry->properties->all())->toEqual(['reference' => $this->lead->reference, 'contact_id' => $this->contact->id, 'fields' => ['phone']])
        ->and($entry->description)->toBe('Contact details taken from this enquiry: phone')
        ->and(json_encode($entry->withoutRelations()->getAttributes()))->not->toContain('9876543210');
});

it('refuses a detail that does not differ from the contact, and changes nothing', function (array $fields) {
    expect(fn () => app(UpdateContactFromLead::class)->handle($this->lead, $this->sales, $fields))
        ->toThrow(ValidationException::class, 'Choose details that differ from the contact');

    expect($this->contact->fresh()->company_name)->toBe('Old Co')
        ->and(Activity::query()->where('event', 'contact_updated')->count())->toBe(0);
})->with([
    'the same email' => [['email']],
    'nothing chosen' => [[]],
    'an unknown field' => [['company', 'notes']],
]);

it('refuses an email or phone another contact already has, so one visitor is not recorded twice', function () {
    Contact::factory()->create(['phone_e164' => '+919876543210']);

    expect(fn () => app(UpdateContactFromLead::class)->handle($this->lead, $this->sales, ['phone', 'company']))
        ->toThrow(ValidationException::class, 'Another contact already has this phone');

    expect($this->contact->fresh()->only(['phone_e164', 'company_name']))->toBe(['phone_e164' => null, 'company_name' => 'Old Co']);
});

it('refuses staff who may not work on the enquiry\'s location, and read-only staff', function (StaffRole $role, bool $assigned, bool $active) {
    $user = User::factory()->withRole($role)->create(['is_active' => $active]);
    if ($assigned) {
        $user->assignedProperties()->attach($this->property);
    }

    expect(fn () => app(UpdateContactFromLead::class)->handle($this->lead, $user, ['company']))
        ->toThrow(AuthorizationException::class);

    expect($this->contact->fresh()->company_name)->toBe('Old Co');
})->with([
    'sales for another location' => [StaffRole::SalesCrmExecutive, false, true],
    'the location\'s property manager' => [StaffRole::PropertyManager, true, true],
    'a deactivated sales executive' => [StaffRole::SalesCrmExecutive, true, false],
]);
