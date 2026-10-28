<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Properties\PropertyStatus;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concurrency\PublishingHarness;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    PublishingHarness::reset();
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
});

afterEach(function () {
    PublishingHarness::reset();
});

it('lets exactly one of two people assign an enquiry at the same instant', function () {
    $lead = Lead::factory()->forProperty($this->property)->create();
    $first = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $second = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $first->assignedProperties()->attach($this->property);
    $second->assignedProperties()->attach($this->property);

    $results = PublishingHarness::runWorkers([
        ['mode' => 'assign_lead', 'lead_id' => $lead->id, 'user_id' => $first->id, 'owner_id' => $first->id],
        ['mode' => 'assign_lead', 'lead_id' => $lead->id, 'user_id' => $second->id, 'owner_id' => $second->id],
    ]);

    expect(collect($results)->where('outcome', 'assigned')->count())->toBe(1)
        ->and(collect($results)->where('outcome', 'refused')->pluck('message')->all())->toBe(['Someone else changed who owns this enquiry. Reload it and try again.'])
        ->and(Activity::query()->where('event', 'lead_assigned')->count())->toBe(1)
        ->and($lead->fresh()->owner_user_id)->toBeIn([$first->id, $second->id]);
})->group('mysql', 'concurrency');

it('stores one contact and one enquiry when the same enquiry is sent twice at the same instant', function () {
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    $enquiry = [
        'started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
        'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => '1',
    ];

    $results = PublishingHarness::runWorkers([
        ['mode' => 'submit_enquiry', 'enquiry' => $enquiry],
        ['mode' => 'submit_enquiry', 'enquiry' => $enquiry],
    ]);

    expect(Lead::query()->count())->toBe(1)
        ->and(Contact::query()->count())->toBe(1)
        ->and(collect($results)->pluck('outcome')->unique()->all())->toBe([Lead::query()->sole()->reference]);
})->group('mysql', 'concurrency');

it('never leaves a property on the website when operations close it while its profile is being published', function () {
    offerForTest($this->property, 'hot_desk', published: false);
    $author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $draft = app(CreateDraft::class)->handle($this->property->profile, $author, ['headline' => 'H', 'summary' => 'S']);
    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $approved->id, 'user_id' => $publisher->id],
        ['mode' => 'close_property', 'property_id' => $this->property->id, 'user_id' => $operations->id],
    ]);

    expect($results['worker-1']['outcome'])->toBe('closed')
        ->and($this->property->fresh()->operational_status)->toBe(PropertyStatus::Closed)
        ->and(Property::query()->publiclyListed()->whereKey($this->property->id)->exists())->toBeFalse()
        ->and($results['worker-0']['outcome'])->toBeIn(['published', 'refused']);
})->group('mysql', 'concurrency');
