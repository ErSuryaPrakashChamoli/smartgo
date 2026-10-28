<?php

use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\Building;
use App\Models\City;
use App\Models\Product;
use App\Models\Property;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('records only the confirmed cities, categories and properties, and can run again', function () {
    seed(ReferenceDataSeeder::class);
    seed(ReferenceDataSeeder::class);

    expect(City::query()->orderBy('id')->pluck('name')->all())->toBe(['Noida', 'Gurugram', 'Delhi', 'Faridabad', 'Ghaziabad'])
        ->and(City::query()->where('is_publicly_visible', true)->count())->toBe(0)
        ->and(City::query()->where('slug', 'gurugram')->value('aliases'))->toBe(['gurgaon'])
        ->and(Product::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Private offices and cabins', 'Dedicated desks', 'Hot desks', 'Meeting rooms', 'Conference and training rooms', 'Virtual office', 'Managed offices'])
        ->and(Property::query()->orderBy('id')->get()->map(fn (Property $property): string => $property->addressLine())->all())
        ->toBe(['A-70, Sector 2, Noida', 'A-71, Sector 2, Noida', 'A-23, Sector 3, Noida', 'H-105, Sector 62, Noida'])
        ->and(Property::query()->where('operating_model', '!=', OperatingModel::OwnedOperated)->exists())->toBeFalse()
        ->and(Property::query()->where('operational_status', '!=', PropertyStatus::Open)->exists())->toBeFalse()
        ->and(Building::query()->count())->toBe(4)
        ->and(Building::query()->whereNotNull('postal_code')->orWhereNotNull('latitude')->exists())->toBeFalse()
        ->and(DB::table('offerings')->count())->toBe(0);
});
