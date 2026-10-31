<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\call;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * The private disk (`local`, storage/app/private) holds media originals, 3D models and inventory
 * workbooks. Laravel serves it at /storage/{path} only with a valid temporary signature (Phase 14
 * review, OD-132 f): an address without one, or with a forged one, is refused, for reading and writing.
 */
beforeEach(function () {
    // A test copy of the private disk with the same serving setting, so the real folder is never touched.
    Storage::fake('local', ['serve' => true]);
});

it('refuses to serve or accept a private file without a valid signature', function () {
    Storage::disk('local')->put('inventory-imports/test-workbook.xlsx', 'test content');

    get('/storage/inventory-imports/test-workbook.xlsx')->assertForbidden();
    get('/storage/inventory-imports/test-workbook.xlsx?expires=9999999999&signature=forged')->assertForbidden();
    call('PUT', '/storage/inventory-imports/test-workbook.xlsx', [], [], [], [], 'replaced')->assertForbidden();

    expect(Storage::disk('local')->get('inventory-imports/test-workbook.xlsx'))->toBe('test content');
});

it('accepts the signature of a temporary address the application made, so the refusals above come from the signature', function () {
    Storage::disk('local')->put('inventory-imports/test-workbook.xlsx', 'test content');
    $url = Storage::disk('local')->temporaryUrl('inventory-imports/test-workbook.xlsx', now()->addMinutes(5));

    expect(get((string) str($url)->after(config('app.url')))->getStatusCode())->not->toBe(403);
});
