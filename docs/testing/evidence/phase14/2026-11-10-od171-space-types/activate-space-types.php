<?php

// OD-171 (A-23): load the six approved space types and the eight approved type-category links, nothing else.
//
// The definitions are read from Database\Seeders\ReferenceDataSeeder (SPACE_TYPES and SPACE_TYPE_CATEGORIES, from
// DATABASE_ERD §3.1), never retyped; the two A-28 combinations are taken out, which leaves exactly ERD §3.1's
// "typically sellable as". One transaction: types are created only where missing (an existing type must match the
// definition exactly, or the script refuses), links are added only where missing, no row is updated or deleted.
// SpaceType's own audit log records each new type ("inventory" / "created"), inside the same transaction.
//
// Usage: php activate-space-types.php <expected database> [--simulate-failure]
//   --simulate-failure throws after every insert, to prove the transaction leaves the database as it was.

use App\Models\Product;
use App\Models\SpaceType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = '/home/administrator/Documents/website/smartGrow';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expected = $argv[1] ?? '';
$simulateFailure = in_array('--simulate-failure', $argv, true);
$refuse = function (string $reason): never {
    fwrite(STDERR, "Refusing: {$reason}\n");
    exit(1);
};

if (DB::selectOne('SELECT DATABASE() AS db')->db !== $expected || $app->configurationIsCached()) {
    $refuse("the connected database is not {$expected}, or the configuration is cached");
}
if (! Schema::hasTable('space_types') || ! Schema::hasTable('product_space_type')) {
    $refuse('space_types or product_space_type does not exist');
}

// The authoritative definitions, as the seeder holds them.
$seeder = new ReflectionClass(ReferenceDataSeeder::class);
$types = $seeder->getConstant('SPACE_TYPES');
$categoriesByType = $seeder->getConstant('SPACE_TYPE_CATEGORIES');

// A-28: undecided, so not approved (SPACES_ACTIVATION_PROCEDURE step 5a).
$excluded = [
    ['desk_zone_flexible', 'dedicated_desk'],           // A-28 (1): flexible seating zone -> dedicated desks
    ['conference_training_room', 'meeting_room'],       // A-28 (2): conference or training room -> meeting rooms
];
$links = [];
foreach ($categoriesByType as $typeKey => $categoryKeys) {
    foreach ($categoryKeys as $categoryKey) {
        if (! in_array([$typeKey, $categoryKey], $excluded, true)) {
            $links[] = [$typeKey, $categoryKey];
        }
    }
}
if (count($types) !== 6 || count($links) !== 8 || array_diff(array_keys($categoriesByType), array_column($types, 'key')) !== []) {
    $refuse('the definitions do not give exactly six types and eight links');
}

$products = Product::query()->whereIn('key', array_unique(array_column($links, 1)))->pluck('id', 'key');
if ($products->count() !== count(array_unique(array_column($links, 1)))) {
    $refuse('a category the links need does not exist');
}

$counts = fn (): array => [
    'space_types' => DB::table('space_types')->count(),
    'product_space_type' => DB::table('product_space_type')->count(),
    'activity_log' => DB::table('activity_log')->count(),
];
$before = $counts();
$createdTypes = [];
$confirmedTypes = [];
$addedLinks = [];
$confirmedLinks = [];
$failure = null;

try {
    DB::transaction(function () use ($types, $links, $products, $simulateFailure, &$createdTypes, &$confirmedTypes, &$addedLinks, &$confirmedLinks, $refuse): void {
        foreach ($types as $position => $definition) {
            $wanted = [...$definition, 'sort_order' => ($position + 1) * 10];
            $existing = SpaceType::query()->where('key', $definition['key'])->lockForUpdate()->first();

            if ($existing === null) {
                SpaceType::query()->create($wanted);
                $createdTypes[] = $definition['key'];

                continue;
            }

            // Compare as the model reads them: an enum by its value, a list as a list.
            $differences = array_keys(array_filter($wanted, fn (mixed $value, string $field): bool => ($existing->{$field} instanceof BackedEnum ? $existing->{$field}->value : $existing->{$field}) != $value, ARRAY_FILTER_USE_BOTH));
            if ($differences !== []) {
                // Never overwrite: someone changed this type, so the owner decides.
                throw new RuntimeException("space type {$definition['key']} exists with different ".implode(', ', $differences));
            }
            $confirmedTypes[] = $definition['key'];
        }

        foreach ($links as [$typeKey, $categoryKey]) {
            $type = SpaceType::query()->where('key', $typeKey)->sole();
            if ($type->products()->whereKey($products[$categoryKey])->exists()) {
                $confirmedLinks[] = "{$typeKey} -> {$categoryKey}";

                continue;
            }
            $type->products()->attach($products[$categoryKey]);
            $addedLinks[] = "{$typeKey} -> {$categoryKey}";
        }

        if ($simulateFailure) {
            throw new RuntimeException('simulated failure after every insert (--simulate-failure)');
        }
    });
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
}

$after = $counts();
$present = DB::table('product_space_type')
    ->join('space_types', 'space_types.id', '=', 'product_space_type.space_type_id')
    ->join('products', 'products.id', '=', 'product_space_type.product_id')
    ->orderBy('space_types.sort_order')->orderBy('products.sort_order')
    ->get(['space_types.key as type', 'products.key as category'])
    ->map(fn (object $link): string => "{$link->type} -> {$link->category}")->all();

echo json_encode([
    'database' => $expected,
    'simulated_failure' => $simulateFailure,
    'result' => $failure === null ? 'committed' : 'rolled back',
    'failure' => $failure,
    'created_types' => $createdTypes,
    'confirmed_types' => $confirmedTypes,
    'added_links' => $addedLinks,
    'confirmed_links' => $confirmedLinks,
    'before' => $before,
    'after' => $after,
    'links_now' => $present,
    'a28_present' => array_values(array_filter(array_map(fn (array $pair): string => "{$pair[0]} -> {$pair[1]}", $excluded), fn (string $link): bool => in_array($link, $present, true))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

exit($failure === null || $simulateFailure ? 0 : 1);
