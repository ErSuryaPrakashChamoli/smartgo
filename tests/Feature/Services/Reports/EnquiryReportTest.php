<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Booking\TourStatus;
use App\Enums\Crm\LeadActivityType;
use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Insights\Pages\ContentFreshness;
use App\Filament\Clusters\Insights\Pages\EnquiryTotals;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Page;
use App\Models\Post;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use App\Services\Reports\EnquiryReport;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-08 06:30:00'); // Monday 8 March 2027, 12:00 in India
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->a70 = Property::query()->where('code', 'A-70')->sole();
    $this->a71 = Property::query()->where('code', 'A-71')->sole();
    $this->admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $staff = $this->admin;
    $lead = fn (string $createdUtc, array $attributes = []) => Lead::factory()->create(['created_at' => $createdUtc, ...$attributes]);
    $contact = fn (Lead $lead, string $atUtc, LeadActivityType $type = LeadActivityType::Call) => LeadActivity::query()->forceCreate(['lead_id' => $lead->id, 'user_id' => $staff->id, 'type' => $type, 'body' => 'Test', 'created_at' => $atUtc, 'updated_at' => $atUtc]);

    // The period 1 to 7 March 2027 in India is 28 February 18:30 UTC to 7 March 18:30 UTC.
    $lead('2027-02-28 18:29:59', ['property_id' => $this->a70->id, 'submitted_name' => 'Before the period']);
    $this->first = $lead('2027-02-28 18:30:00', ['property_id' => $this->a70->id, 'status' => LeadStatus::Contacted, 'utm_source' => 'newsletter', 'submitted_name' => 'Asha Verma', 'submitted_email' => 'asha@example.com']);
    $this->second = $lead('2027-03-03 05:00:00', ['property_id' => $this->a71->id, 'source' => Lead::SOURCE_TOUR_FORM, 'status' => LeadStatus::Qualified]);
    $this->third = $lead('2027-03-05 10:00:00', ['property_id' => null, 'status' => LeadStatus::Lost]);
    $this->last = $lead('2027-03-07 18:29:59', ['property_id' => $this->a70->id]);
    $lead('2027-03-07 18:30:00', ['property_id' => $this->a70->id, 'submitted_name' => 'After the period']);

    $contact($this->first, '2027-02-28 19:00:00');
    $contact($this->second, '2027-03-03 06:30:00', LeadActivityType::Email);
    $contact($this->second, '2027-03-03 07:00:00');
    $contact($this->third, '2027-03-05 20:00:00', LeadActivityType::Meeting);
    $contact($this->last, '2027-03-08 00:00:00', LeadActivityType::Note);
    LeadActivity::query()->forceCreate(['lead_id' => $this->last->id, 'user_id' => $staff->id, 'type' => LeadActivityType::FollowUp, 'body' => 'Call back', 'due_at' => '2027-03-08 05:00:00', 'created_at' => now(), 'updated_at' => now()]);
    TourRequest::factory()->create(['lead_id' => $this->second->id, 'property_id' => $this->a71->id, 'created_at' => '2027-03-03 06:00:00', 'scheduled_start_at' => '2027-03-09 05:00:00', 'status' => TourStatus::Confirmed, 'channel' => TourRequest::CHANNEL_STAFF]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    // Carbon's locale is global and outlives the application of one test.
    app()->setLocale(config('app.locale'));
});

function reportForTest(User $user, ?int $propertyId = null, string $from = '2027-03-01', string $to = '2027-03-07'): array
{
    return app(EnquiryReport::class)->build($user, CarbonImmutable::parse($from), CarbonImmutable::parse($to), $propertyId);
}

it('counts the enquiries received in whole India-time days, matching an independent query on the same rules', function () {
    $report = reportForTest($this->admin);
    $independent = DB::table('leads')->where('created_at', '>=', '2027-02-28 18:30:00')->where('created_at', '<', '2027-03-07 18:30:00');

    expect($report['received'])->toBe(4)->toBe($independent->count())
        ->and(collect($report['over_time'])->sum('total'))->toBe(4)
        ->and(collect($report['over_time'])->firstWhere('label', 'Mon 1 Mar 2027')['total'])->toBe(1)
        ->and(collect($report['over_time'])->firstWhere('label', 'Sun 7 Mar 2027')['total'])->toBe(1)
        ->and(count($report['over_time']))->toBe(7)
        ->and(collect($report['by_status'])->pluck('total', 'label')->all())->toEqual((clone $independent)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
        ->mapWithKeys(fn ($n, $status) => [LeadStatus::from($status)->label() => (int) $n])->all())
        ->and(collect($report['by_source'])->pluck('total', 'label')->all())->toEqual(['Website enquiry form' => 3, 'Website tour request' => 1])
        ->and(collect($report['by_location'])->pluck('total', 'label')->all())->toEqual(['SmartGro A-70' => 2, 'SmartGro A-71' => 1, 'No location chosen' => 1])
        ->and(collect($report['by_campaign'])->pluck('total', 'label')->all())->toEqual(['No campaign recorded' => 3, 'newsletter' => 1])
        ->and($report['led_to_tour'])->toBe(1);
});

it('measures time to the first recorded contact, not a note or a status change, without any target', function () {
    $contact = reportForTest($this->admin)['first_contact'];

    // 30 minutes (call), 90 minutes (the earlier email), 600 minutes (meeting); the last one has only a note.
    expect($contact)->toBe(['contacted' => 3, 'not_contacted' => 1, 'median_minutes' => 90, 'p90_minutes' => 600]);
});

it('groups longer periods by week, and shows the workload now and the tours in the period', function () {
    $report = reportForTest($this->admin, from: '2027-01-04', to: '2027-03-07');

    expect($report['over_time'][0]['label'])->toBe('Week of 4 Jan 2027')
        ->and(collect($report['over_time'])->sum('total'))->toBe(DB::table('leads')->where('created_at', '>=', '2027-01-03 18:30:00')->where('created_at', '<', '2027-03-07 18:30:00')->count())
        ->and($report['workload'])->toBe(['unassigned_open' => DB::table('leads')->whereIn('status', ['new', 'contacted', 'qualified'])->whereNull('owner_user_id')->count(), 'follow_ups_overdue' => 1, 'follow_ups_due_today' => 0])
        ->and($report['tours']['requested'])->toBe(1)
        ->and($report['tours']['requested_by_channel'])->toBe([['label' => 'Arranged by staff', 'total' => 1]])
        ->and(reportForTest($this->admin)['tours']['taking_place'])->toBe(0);
});

it('keeps each person to their property scope: a Property Manager sees their properties only, Marketing no tours, Finance nothing', function () {
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->a70);
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $operations = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();

    expect(reportForTest($manager)['received'])->toBe(2)
        ->and(reportForTest($manager, $this->a71->id)['received'])->toBe(0)
        ->and(reportForTest($marketing)['received'])->toBe(4)
        ->and(reportForTest($marketing)['tours'])->toBeNull()
        ->and(reportForTest($operations)['tours']['requested'])->toBe(1);

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create());
    get(EnquiryTotals::getUrl())->assertForbidden();
});

it('shows the totals with their definitions and never a visitor\'s details, and refuses an impossible period', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create());

    Livewire::test(EnquiryTotals::class)
        ->set('filters.from', '2027-03-01')->set('filters.to', '2027-03-07')
        ->assertSee(['Enquiries received', 'Median time to first contact', 'No target is set (D-09)', 'Website enquiry form', 'SmartGro A-71', 'tracking stays off'])
        ->assertDontSee(['Asha Verma', 'asha@example.com', 'Before the period', 'After the period'])
        ->set('filters.from', '2027-03-08')
        ->assertSee('The first day must not be after the last day.')
        ->set('filters.from', '2025-01-01')
        ->assertSee('Choose at most 366 days.');
});

it('lists live content oldest first for content staff, and nothing that is not published', function () {
    $old = Page::factory()->published()->create(['slug' => 'old-page', 'title' => 'An old page', 'published_at' => '2026-06-01 06:30:00']);
    $new = Post::factory()->published()->create(['slug' => 'new-article', 'title' => 'A new article', 'published_at' => '2027-03-01 06:30:00']);
    Post::factory()->create(['slug' => 'draft', 'title' => 'A draft article']);
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create());

    Livewire::test(ContentFreshness::class)
        ->assertCanSeeTableRecords(['Page-'.$old->id, 'Article-'.$new->id], inOrder: true)
        ->assertDontSee('A draft article');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create());
    get(ContentFreshness::getUrl())->assertForbidden();
});

it('builds with the same number of queries for 2,000 enquiries as for a few, in bounded time', function () {
    $count = function (): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = hrtime(true);
        reportForTest($this->admin, from: '2027-01-01', to: '2027-03-07');
        $milliseconds = (hrtime(true) - $started) / 1e6;
        DB::disableQueryLog();

        return [count(DB::getQueryLog()), $milliseconds];
    };
    $count(); // the first build also loads permissions and checks the tour tables once
    [$few] = $count();
    $contact = $this->first->contact_id;
    DB::table('leads')->insert(collect(range(1, 2000))->map(fn (int $i): array => [
        'reference' => sprintf('ENQ-V%07d', $i), 'contact_id' => $contact, 'source' => Lead::SOURCE_WEBSITE_FORM, 'status' => 'new',
        'submitted_name' => 'Volume test', 'property_id' => $i % 2 === 0 ? $this->a70->id : $this->a71->id, 'consent_snapshot' => '{}',
        'created_at' => CarbonImmutable::parse('2027-01-01 00:00:00')->addMinutes($i * 45), 'updated_at' => now(),
    ])->all());
    [$many, $milliseconds] = $count();

    expect($many)->toBe($few)->and($milliseconds)->toBeLessThan(5000);
});

it('narrows to one location exactly as an independent query does, and shows an empty period as zeros', function () {
    $filtered = reportForTest($this->admin, $this->a71->id);
    $empty = reportForTest($this->admin, from: '2026-01-01', to: '2026-01-07');

    expect($filtered['received'])->toBe(DB::table('leads')->where('property_id', $this->a71->id)->where('created_at', '>=', '2027-02-28 18:30:00')->where('created_at', '<', '2027-03-07 18:30:00')->count())
        ->and($filtered['by_location'])->toBe([['label' => 'SmartGro A-71', 'total' => 1]])
        ->and($empty['received'])->toBe(0)
        ->and(collect($empty['over_time'])->pluck('total')->all())->toBe([0, 0, 0, 0, 0, 0, 0])
        ->and($empty['by_status'])->toBe([])
        ->and($empty['first_contact'])->toBe(['contacted' => 0, 'not_contacted' => 0, 'median_minutes' => null, 'p90_minutes' => null]);

    actingAs($this->admin);
    Livewire::test(EnquiryTotals::class)->set('filters.from', '2026-01-01')->set('filters.to', '2026-01-07')->assertSee('None in this period.');
});

it('limits a Sales/CRM Executive to their assigned locations unless recorded as working on every property', function () {
    $sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create();
    $sales->assignedProperties()->attach($this->a71);

    expect(reportForTest($sales)['received'])->toBe(1)
        ->and(reportForTest($sales)['by_location'])->toBe([['label' => 'SmartGro A-71', 'total' => 1]]);

    $sales->forceFill(['has_all_property_access' => true])->save();

    expect(reportForTest($sales->fresh())['received'])->toBe(4);
});

it('says on the page whether enquiries over time are counted per day or per week', function () {
    actingAs($this->admin);

    Livewire::test(EnquiryTotals::class)->set('filters.from', '2027-01-04')->set('filters.to', '2027-03-07')
        ->assertSee('Week of 4 Jan 2027')->assertSee('Per week, Monday to Sunday.')->assertDontSee('Per day.')
        ->set('filters.from', '2027-03-01')
        ->assertSee('Mon 1 Mar 2027')->assertSee('Per day.')->assertDontSee('Per week, Monday to Sunday.');
});

it('counts follow-ups due today in India time and the tours in a period exactly as independent queries do', function () {
    $followUp = fn (string $dueUtc, ?string $completedUtc = null) => LeadActivity::query()->forceCreate(['lead_id' => $this->first->id, 'user_id' => $this->admin->id, 'type' => LeadActivityType::FollowUp, 'body' => 'Call back', 'due_at' => $dueUtc, 'completed_at' => $completedUtc, 'created_at' => now(), 'updated_at' => now()]);
    $followUp('2027-03-08 18:29:59'); // 23:59:59 today in India
    $followUp('2027-03-08 18:30:00'); // midnight, tomorrow in India
    $followUp('2027-03-08 02:00:00', '2027-03-08 03:00:00'); // done
    TourRequest::factory()->forProperty($this->a70)->create(['lead_id' => $this->first->id, 'created_at' => '2027-03-08 18:29:59', 'scheduled_start_at' => '2027-03-10 05:00:00', 'status' => TourStatus::Cancelled]);
    TourRequest::factory()->forProperty($this->a70)->confirmed()->create(['lead_id' => $this->first->id, 'created_at' => '2027-03-01 05:00:00', 'scheduled_start_at' => '2027-03-14 18:29:59']);
    TourRequest::factory()->forProperty($this->a71)->create(['lead_id' => $this->second->id, 'created_at' => '2027-03-14 18:30:00', 'scheduled_start_at' => '2027-03-14 18:30:00']);

    $report = reportForTest($this->admin, from: '2027-03-08', to: '2027-03-14');
    $created = DB::table('tour_requests')->where('created_at', '>=', '2027-03-07 18:30:00')->where('created_at', '<', '2027-03-14 18:30:00');
    $taking = DB::table('tour_requests')->where('scheduled_start_at', '>=', '2027-03-07 18:30:00')->where('scheduled_start_at', '<', '2027-03-14 18:30:00');
    $byStatus = fn ($query) => (clone $query)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->mapWithKeys(fn ($n, $status) => [TourStatus::from($status)->label() => (int) $n])->all();

    expect($report['workload']['follow_ups_due_today'])->toBe(1)
        ->and($report['workload']['follow_ups_overdue'])->toBe(DB::table('lead_activities')->where('type', 'follow_up')->whereNull('completed_at')->where('due_at', '<', now())->count())
        ->and($report['tours']['requested'])->toBe(1)->toBe($created->count())
        ->and(collect($report['tours']['requested_by_status'])->pluck('total', 'label')->all())->toEqual($byStatus($created))
        ->and($report['tours']['requested_by_location'])->toBe([['label' => 'SmartGro A-70', 'total' => 1]])
        ->and($report['tours']['taking_place'])->toBe(3)->toBe($taking->count())
        ->and(collect($report['tours']['taking_place_by_status'])->pluck('total', 'label')->all())->toEqual($byStatus($taking));
});

it('keeps its days in the back-office time zone and its weeks starting on Monday, whatever the zone and language settings', function () {
    config(['backoffice.timezone' => 'Asia/Dubai']);
    app()->setLocale('hi');
    // 19:30 UTC on 1 March is 23:30 that day in Dubai, but already 2 March in India.
    Lead::factory()->create(['created_at' => '2027-03-01 19:30:00', 'property_id' => $this->a70->id]);
    $day = reportForTest($this->admin, from: '2027-03-01', to: '2027-03-01');
    $weeks = reportForTest($this->admin, from: '2027-01-04', to: '2027-03-07');

    expect(CarbonImmutable::parse('2027-01-06')->startOfWeek()->isSunday())->toBeTrue()
        ->and($day['received'])->toBe(1)
        ->and($day['over_time'])->toBe([['label' => 'Mon 1 Mar 2027', 'total' => 1]])
        ->and($weeks['over_time'][0]['label'])->toBe('Week of 4 Jan 2027')
        ->and(collect($weeks['over_time'])->sum('total'))->toBe($weeks['received']);
});
