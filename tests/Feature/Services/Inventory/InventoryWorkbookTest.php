<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\City;
use App\Models\Product;
use App\Models\SpaceType;
use App\Models\User;
use App\Services\Inventory\Import\WorkbookReader;
use App\Services\Inventory\Import\WorkbookValidator;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

/*
 * Conformance with INVENTORY_IMPORT_SPEC §7: the synthetic fixtures (test data only, never a stand-in for
 * real inventory) under the spec's test conditions, time frozen at 2026-10-24 and the synthetic Property
 * Manager seeded, plus their city: a city is added in the back office before a property is imported
 * (OD-128 f), so the fictional "Synthville" is created here like the staff user.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 06:00:00'));
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    City::query()->forceCreate(['name' => 'Synthville', 'slug' => 'synthville', 'state' => 'Test State', 'aliases' => [], 'is_target_market' => false, 'is_publicly_visible' => false]);
    User::factory()->withRole(StaffRole::PropertyManager)->create(['email' => 'synthetic.manager@example.com']);
    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
});

function validateFixtureForTest(string $name, User $uploader): array
{
    $path = base_path('tests/Fixtures/Inventory/'.$name);

    return app(WorkbookValidator::class)->validate(app(WorkbookReader::class)->read($path), filesize($path), $uploader);
}

it('finds no error and no warning in the valid synthetic workbook', function () {
    $result = validateFixtureForTest('SYNTHETIC_inventory_valid.xlsx', $this->operations);

    expect(collect($result['issues'])->whereIn('severity', ['error', 'warning'])->values()->all())->toBe([])
        ->and(array_map('count', $result['records']))->toBe(['Amenities' => 5, 'Property' => 2, 'Buildings' => 3, 'Hours' => 14, 'Floors' => 4, 'Spaces' => 15, 'Seats' => 22, 'Occupancy' => 10]);
});

it('refuses a space sold as a category that does not accept its space type (V-117, BR-PRODCFG-2, OD-153)', function () {
    Product::query()->where('key', 'meeting_room')->sole()->spaceTypes()->detach(SpaceType::query()->where('key', 'conference_training_room')->value('id'));

    $result = validateFixtureForTest('SYNTHETIC_inventory_valid.xlsx', $this->operations);

    expect(collect($result['issues'])->where('severity', 'error')->map(fn (array $issue): array => [$issue['sheet'], $issue['row'], $issue['column'], $issue['rule'], $issue['message']])->values()->all())->toBe([
        ['Spaces', 11, 'sellable_as', 'V-117', 'A conference / training room cannot be sold as Meeting rooms: that category does not accept this space type (BR-PRODCFG-2).'],
    ]);
});

it('reports exactly the issues the error workbook expects, by sheet, row, column, rule and severity', function () {
    $path = base_path('tests/Fixtures/Inventory/SYNTHETIC_inventory_with_errors.xlsx');
    $reader = new Reader;
    $reader->open($path);
    $expected = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        if ($sheet->getName() === '_expected_issues') {
            foreach ($sheet->getRowIterator() as $number => $row) {
                $cells = $row->toArray();

                if ($number > 1) {
                    $expected[] = implode('|', [$cells[0], (int) $cells[1], $cells[2], $cells[3], $cells[4]]);
                }
            }
        }
    }

    $reader->close();
    $found = collect(validateFixtureForTest('SYNTHETIC_inventory_with_errors.xlsx', $this->operations)['issues'])
        ->whereIn('severity', ['error', 'warning'])
        ->map(fn (array $issue): string => implode('|', [$issue['sheet'], $issue['row'], $issue['column'], $issue['rule'], $issue['severity']]))
        ->sort()->values()->all();

    sort($expected);

    expect($found)->toBe($expected)->and(count($expected))->toBe(20);
});
