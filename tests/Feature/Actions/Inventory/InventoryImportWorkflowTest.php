<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Inventory\ChangeInventoryImportState;
use App\Actions\Inventory\CommitInventoryImport;
use App\Actions\Inventory\UploadInventoryImport;
use App\Enums\Access\StaffRole;
use App\Enums\Inventory\ImportStatus;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Filament\Clusters\Properties\Resources\InventoryImports\InventoryImportResource;
use App\Filament\Clusters\Properties\Resources\InventoryImports\Pages\ViewInventoryImport;
use App\Models\Amenity;
use App\Models\Building;
use App\Models\City;
use App\Models\Floor;
use App\Models\InventoryImport;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceSeat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-24 06:00:00'));
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    City::query()->forceCreate(['name' => 'Synthville', 'slug' => 'synthville', 'state' => 'Test State', 'aliases' => [], 'is_target_market' => false, 'is_publicly_visible' => false]);
    User::factory()->withRole(StaffRole::PropertyManager)->create(['email' => 'synthetic.manager@example.com']);
    $this->uploader = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();
});

function fixtureUploadForTest(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents(base_path('tests/Fixtures/Inventory/'.$name)));
}

/**
 * A small workbook written for a malformed-file test.
 *
 * @param  array<string, list<list<mixed>>>  $sheets
 */
function workbookForTest(array $sheets): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'sginv').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $first = true;

    foreach ($sheets as $name => $rows) {
        $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($name);
        $first = false;

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
    }

    $writer->close();

    return UploadedFile::fake()->createWithContent('workbook.xlsx', (string) file_get_contents($path));
}

function rulesForTest(InventoryImport $import): array
{
    return $import->issues()->where('severity', 'error')->pluck('rule')->unique()->sort()->values()->all();
}

it('checks, previews, and imports real-format inventory only after someone other than the uploader approves it, all at once', function () {
    Event::fake([SpaceAvailabilityChanged::class]);
    $import = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));

    expect($import->status)->toBe(ImportStatus::Valid)
        ->and($import->summary['counts']['Spaces'])->toBe(['new' => 15, 'updated' => 0, 'unchanged' => 0, 'not_in_file' => 0])
        ->and(Property::query()->where('code', 'SYN-ALPHA')->exists())->toBeFalse()
        ->and(fn () => app(CommitInventoryImport::class)->handle($this->uploader, $import))->toThrow(AuthorizationException::class);

    app(ChangeInventoryImportState::class)->handle($this->uploader, $import, 'submit');
    $done = app(CommitInventoryImport::class)->handle($this->approver, $import->fresh());
    $alpha = Property::query()->where('code', 'SYN-ALPHA')->sole();

    expect($done->status)->toBe(ImportStatus::Committed)
        ->and($done->approved_by)->toBe($this->approver->id)
        ->and([Building::query()->count() - 4, Floor::query()->count(), Space::query()->count(), SpaceSeat::query()->count(), SpaceAllocation::query()->count(), Amenity::query()->count()])->toBe([3, 4, 15, 22, 10, 5])
        ->and($alpha->operating_hours['mon'])->toBe(['opens' => '08:00', 'closes' => '20:00'])
        ->and($alpha->profile()->exists())->toBeTrue()
        ->and(Space::query()->where('code', 'SYN-ALPHA-04-CT-01')->sole()->offerings()->with('product')->get()->pluck('product.key')->sort()->values()->all())->toBe(['conference_room', 'meeting_room'])
        ->and(Space::query()->where('code', 'SYN-ALPHA-04-MR-01')->sole()->only(['turnaround_minutes', 'min_booking_minutes', 'max_booking_minutes']))->toBe(['turnaround_minutes' => 15, 'min_booking_minutes' => 30, 'max_booking_minutes' => 480])
        ->and(Activity::query()->where('event', 'imported_created')->count())->toBe(2 + 3 + 4 + 15 + 22 + 10 + 5)
        ->and(Activity::query()->where('event', 'inventory_import_committed')->sole()->causer_id)->toBe($this->approver->id);
    Event::assertDispatchedTimes(SpaceAvailabilityChanged::class, 15);

    $again = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));
    app(ChangeInventoryImportState::class)->handle($this->uploader, $again, 'submit');
    app(CommitInventoryImport::class)->handle($this->approver, $again->fresh());

    expect($again->summary['already_imported'])->toStartWith('This exact file was already imported on 24 Oct 2026')
        ->and($again->summary['counts']['Spaces'])->toBe(['new' => 0, 'updated' => 0, 'unchanged' => 15, 'not_in_file' => 0])
        ->and(collect($again->summary['counts'])->sum('updated'))->toBe(0)
        ->and(Activity::query()->whereIn('event', ['imported_created', 'imported_updated'])->count())->toBe(61)
        ->and(Space::query()->count())->toBe(15);
});

it('refuses to submit a workbook with errors, and reports malformed files row by row without importing anything', function () {
    $broken = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_with_errors.xlsx'));

    expect($broken->status)->toBe(ImportStatus::Invalid)
        ->and($broken->issues()->whereIn('severity', ['error', 'warning'])->count())->toBe(20)
        ->and($broken->issues()->where('rule', 'W-170')->sole()->value_excerpt)->toBe('[email removed]')
        ->and(fn () => app(ChangeInventoryImportState::class)->handle($this->uploader, $broken, 'submit'))->toThrow(ValidationException::class);

    $notWorkbook = app(UploadInventoryImport::class)->handle($this->uploader, UploadedFile::fake()->createWithContent('inventory.xlsx', 'not a spreadsheet'));
    $wrongTemplate = app(UploadInventoryImport::class)->handle($this->uploader, workbookForTest(['Property' => [['property_code']], '_meta' => [['template_id', 'SG-PRICES-1.0']]]));
    $missingParts = app(UploadInventoryImport::class)->handle($this->uploader, workbookForTest(['Property' => [['property_code', 'property_name']], '_meta' => [['template_id', 'SG-INV-1.3']]]));

    expect(rulesForTest($notWorkbook))->toBe(['V-004'])
        ->and(rulesForTest($wrongTemplate))->toBe(['V-001'])
        ->and(rulesForTest($missingParts))->toBe(['V-002', 'V-003'])
        ->and(fn () => app(UploadInventoryImport::class)->handle($this->uploader, UploadedFile::fake()->create('inventory.csv', 1)))->toThrow(ValidationException::class, 'Upload the filled-in SG-INV template as an .xlsx file of at most 10 MB.')
        ->and(Space::query()->count())->toBe(0);
});

it('writes nothing when the commit fails part-way, and marks the import failed', function () {
    $import = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));
    app(ChangeInventoryImportState::class)->handle($this->uploader, $import, 'submit');
    Space::creating(function (Space $space): void {
        if ($space->code === 'SYN-BETA-01-MR-01') {
            throw new RuntimeException('Simulated failure on the last space');
        }
    });

    expect(fn () => app(CommitInventoryImport::class)->handle($this->approver, $import->fresh()))->toThrow(ValidationException::class, 'The import failed and nothing was written.')
        ->and([Property::query()->where('code', 'like', 'SYN-%')->count(), Building::query()->where('code', 'like', 'SYN-%')->count(), Space::query()->count(), SpaceAllocation::query()->count(), Amenity::query()->count()])->toBe([0, 0, 0, 0, 0])
        ->and($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and(Activity::query()->where('event', 'imported_created')->count())->toBe(0);
});

it('imports nothing when SmartGro changed after the preview the uploader submitted', function () {
    $import = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));
    app(ChangeInventoryImportState::class)->handle($this->uploader, $import, 'submit');
    Amenity::query()->forceCreate(['name' => 'Wi-Fi', 'slug' => 'wi-fi', 'category' => 'technology', 'applies_to' => 'both']);

    expect(fn () => app(CommitInventoryImport::class)->handle($this->approver, $import->fresh()))->toThrow(ValidationException::class, 'SmartGro changed since this workbook was checked')
        ->and(Space::query()->count())->toBe(0)
        ->and($import->fresh()->status)->toBe(ImportStatus::AwaitingApproval);

    app(ChangeInventoryImportState::class)->handle($this->uploader, $import->fresh(), 'revalidate');

    expect($import->fresh()->status)->toBe(ImportStatus::Valid);
});

it('refuses synthetic test data outside local and testing, and limits Property Managers to their own properties', function () {
    app()->detectEnvironment(fn (): string => 'production');
    $production = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));

    expect(rulesForTest($production))->toContain('V-180');

    app()->detectEnvironment(fn (): string => 'testing');
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach(Property::query()->where('code', 'A-70')->sole());
    $scoped = app(UploadInventoryImport::class)->handle($manager, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));

    expect(rulesForTest($scoped))->toBe(['SCOPE'])
        ->and(fn () => app(UploadInventoryImport::class)->handle(User::factory()->withRole(StaffRole::MarketingManager)->create(), fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx')))->toThrow(AuthorizationException::class);
});

it('shows the findings and preview, and lets only another approver import it from the screen', function () {
    $import = app(UploadInventoryImport::class)->handle($this->uploader, fixtureUploadForTest('SYNTHETIC_inventory_valid.xlsx'));

    actingAs($this->uploader)->get(InventoryImportResource::getUrl('view', ['record' => $import]))->assertOk()->assertSee(['What the import would change', 'Spaces', 'Checked, not submitted']);
    Livewire::test(ViewInventoryImport::class, ['record' => $import->getRouteKey()])
        ->assertActionHidden(TestAction::make('approve'))
        ->callAction(TestAction::make('submit'))
        ->assertNotified('Submitted for approval');

    actingAs($this->approver);
    Livewire::test(ViewInventoryImport::class, ['record' => $import->getRouteKey()])
        ->callAction(TestAction::make('approve'))
        ->assertNotified('Imported');

    expect($import->fresh()->status)->toBe(ImportStatus::Committed)->and(Space::query()->count())->toBe(15);
});
