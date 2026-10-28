<?php

use App\Actions\Twin\CreateTwinModel;
use App\Enums\Twin\CaptureType;
use App\Models\Offering;
use App\Models\Product;
use App\Models\Property;
use App\Models\TwinModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\Twin\TestGlb;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Puts a property's website profile in the state approving and publishing it leaves it, so the
 * property is on the website while its state allows it. Workflow tests publish through the actions.
 *
 * @param  array<string, mixed>  $content
 */
function publishPropertyProfileForTest(Property $property, array $content = []): Property
{
    $property->profile()->firstOrCreate([])->forceFill([
        'headline' => 'Workspaces at SmartGro '.$property->name,
        'summary' => 'Summary of SmartGro '.$property->name.'.',
        ...$content,
        'status' => 'published',
        'published_at' => now(),
    ])->save();

    return $property->fresh();
}

/**
 * Records that a property offers a category, with its offering profile published unless asked not to.
 *
 * @param  array<string, mixed>  $content
 */
function offerForTest(Property $property, string $productKey, bool $published = true, array $content = []): Offering
{
    $productId = Product::query()->where('key', $productKey)->sole()->getKey();
    $offering = Offering::query()->where('property_id', $property->getKey())->where('product_id', $productId)->first()
        ?? Offering::query()->forceCreate(['property_id' => $property->getKey(), 'product_id' => $productId, 'is_active' => true]);

    $offering->profile()->firstOrCreate([])->forceFill($published ? [
        'headline' => 'Headline of '.$productKey.' at '.$property->name,
        'description' => 'Description of '.$productKey.'.',
        'included' => ['Wi-Fi'],
        'not_included' => ['Parking'],
        ...$content,
        'status' => 'published',
        'published_at' => now(),
    ] : $content)->save();

    return $offering->fresh();
}

/**
 * Uploads a 3D model through the real action: the test-only model (TestGlb) unless other bytes are given.
 *
 * @param  array<string, mixed>  $details
 */
function uploadTwinModelForTest(User $uploader, Property $property, array $details = [], ?string $bytes = null, string $fileName = 'model.glb'): TwinModel
{
    return app(CreateTwinModel::class)->handle($uploader, UploadedFile::fake()->createWithContent($fileName, $bytes ?? TestGlb::model()), [
        'property_id' => $property->getKey(),
        'title' => 'Test model',
        'capture_type' => CaptureType::ConceptualModel->value,
        ...$details,
    ]);
}
