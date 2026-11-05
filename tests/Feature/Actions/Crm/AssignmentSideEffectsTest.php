<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\AssignNewLead;
use App\Actions\Crm\SubmitEnquiry;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\AssignmentOutcome;
use App\Models\AssignmentRule;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['has_all_property_access' => true]);
});

/**
 * Every channel the application could use to tell someone about an enquiry, faked so nothing leaves the
 * test: notifications (mail and database), mail, queued jobs and outbound HTTP (O-1, OD-111 d).
 */
function fakeEveryOutgoingChannel(): void
{
    Notification::fake();
    Mail::fake();
    Queue::fake();
    Http::fake();
}

function assertNothingWasSent(): void
{
    Notification::assertNothingSent();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
}

it('sends nothing when a rule assigns an enquiry, when no rule matches and when assignment fails (O-1)', function (string $case) {
    fakeEveryOutgoingChannel();
    $lead = Lead::factory()->forProperty($this->property)->create();

    if ($case !== 'unmatched') {
        AssignmentRule::factory()->active()->withAssignees([$this->sales])->create();
    }

    if ($case === 'failed') {
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower($query->sql), 'insert into `lead_assignments`')) {
                throw new RuntimeException('simulated failure');
            }
        });
    }

    $decision = app(AssignNewLead::class)->handleSafely($lead);

    expect($decision?->outcome)->toBe(match ($case) {
        'assigned' => AssignmentOutcome::Assigned,
        'unmatched' => AssignmentOutcome::NoRuleMatched,
        'failed' => null,
    });
    assertNothingWasSent();
})->with(['assigned', 'unmatched', 'failed']);

it('sends nothing when a website enquiry is stored and given an owner by a rule (O-1)', function () {
    fakeEveryOutgoingChannel();
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    AssignmentRule::factory()->active()->withAssignees([$this->sales])->create();

    $lead = app(SubmitEnquiry::class)->handle(['started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()), 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => '1'], '127.0.0.1');

    expect($lead->fresh()->owner_user_id)->toBe($this->sales->id);
    assertNothingWasSent();
});

it('stores a website enquiry without reading or writing the assignment tables on a database that lacks them', function () {
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, AssignmentRule::TABLES, true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $assignmentQueries = [];
    DB::listen(function ($query) use (&$assignmentQueries): void {
        if (preg_match('/`(assignment_rules|assignment_rule_user|lead_assignments)`/', $query->sql) === 1) {
            $assignmentQueries[] = $query->sql;
        }
    });

    $lead = app(SubmitEnquiry::class)->handle(['started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()), 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => '1'], '127.0.0.1');

    expect($lead->exists)->toBeTrue()
        ->and($lead->fresh()->owner_user_id)->toBeNull()
        ->and($assignmentQueries)->toBe([]);
});

it('locks the enquiry, then the active rules, before reading anything else in the assignment (the M1 invariant, ADR-0004)', function () {
    AssignmentRule::factory()->active()->withAssignees([$this->sales])->create();
    $lead = Lead::factory()->forProperty($this->property)->create();
    AssignmentRule::isAvailable();
    $selects = [];
    DB::listen(function ($query) use (&$selects): void {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
            $selects[] = strtolower($query->sql);
        }
    });

    app(AssignNewLead::class)->handle($lead);

    expect($selects[0])->toStartWith('select * from `leads` where `leads`.`id` = ?')->toEndWith('for update')
        ->and($selects[1])->toStartWith('select * from `assignment_rules` where `is_active` = ?')->toEndWith('for update')
        ->and(collect(array_slice($selects, 0, 2))->every(fn (string $sql): bool => str_ends_with($sql, 'for update')))->toBeTrue();
});
