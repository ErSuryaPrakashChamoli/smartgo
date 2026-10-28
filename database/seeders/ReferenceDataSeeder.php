<?php

namespace Database\Seeders;

use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\City;
use App\Models\CustomerSegment;
use App\Models\MicroMarket;
use App\Models\Product;
use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Only facts the owner has confirmed, so it can run on any environment and run again safely:
 * the five cities with their official names (OD-03, OD-16), the seven workspace categories (OD-07)
 * the eight customer segments (OD-04), and the four Noida properties (OD-08, OD-13), owned and
 * operated (OD-14), open because Noida is the operating city (OD-03). A property is on the website only
 * once its profile is approved and published, so the seeder publishes nothing. Nothing is recorded about which property offers which category, nor about
 * floors, capacity, prices or availability: staff enter those from real information. Each property
 * gets an empty, unpublished website profile for staff to write.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * @var list<array{name: string, slug: string, state: string, aliases: list<string>}>
     */
    private const array CITIES = [
        ['name' => 'Noida', 'slug' => 'noida', 'state' => 'Uttar Pradesh', 'aliases' => []],
        ['name' => 'Gurugram', 'slug' => 'gurugram', 'state' => 'Haryana', 'aliases' => ['gurgaon']],
        ['name' => 'Delhi', 'slug' => 'delhi', 'state' => 'Delhi', 'aliases' => []],
        ['name' => 'Faridabad', 'slug' => 'faridabad', 'state' => 'Haryana', 'aliases' => []],
        ['name' => 'Ghaziabad', 'slug' => 'ghaziabad', 'state' => 'Uttar Pradesh', 'aliases' => []],
    ];

    /**
     * @var list<array{key: string, name: string, slug: string}>
     */
    private const array PRODUCTS = [
        ['key' => 'private_office', 'name' => 'Private offices and cabins', 'slug' => 'private-cabins'],
        ['key' => 'dedicated_desk', 'name' => 'Dedicated desks', 'slug' => 'dedicated-desks'],
        ['key' => 'hot_desk', 'name' => 'Hot desks', 'slug' => 'hot-desks'],
        ['key' => 'meeting_room', 'name' => 'Meeting rooms', 'slug' => 'meeting-rooms'],
        ['key' => 'conference_room', 'name' => 'Conference and training rooms', 'slug' => 'conference-training-rooms'],
        ['key' => 'virtual_office', 'name' => 'Virtual office', 'slug' => 'virtual-office'],
        ['key' => 'managed_office', 'name' => 'Managed offices', 'slug' => 'managed-offices'],
    ];

    /**
     * The eight customer segments in the owner's words (OD-04), with no priority.
     *
     * @var list<string>
     */
    private const array SEGMENTS = [
        'Startups and small businesses',
        'Freelancers and independent professionals',
        'SMEs and growing companies',
        'Large enterprises and corporates',
        'Remote and hybrid employees',
        'Consultants and business travellers',
        'Companies seeking managed offices',
        'Businesses needing virtual offices or registered addresses',
    ];

    /**
     * @var list<array{code: string, sector: string}>
     */
    private const array PROPERTIES = [
        ['code' => 'A-70', 'sector' => 'Sector 2'],
        ['code' => 'A-71', 'sector' => 'Sector 2'],
        ['code' => 'A-23', 'sector' => 'Sector 3'],
        ['code' => 'H-105', 'sector' => 'Sector 62'],
    ];

    public function run(): void
    {
        foreach (self::CITIES as $city) {
            City::query()->firstOrCreate(['slug' => $city['slug']], [...$city, 'is_target_market' => true, 'is_publicly_visible' => false]);
        }

        foreach (self::PRODUCTS as $position => $product) {
            Product::query()->firstOrCreate(['key' => $product['key']], [...$product, 'sort_order' => ($position + 1) * 10, 'is_active' => true]);
        }

        foreach (self::SEGMENTS as $position => $segment) {
            CustomerSegment::query()->firstOrCreate(['slug' => str($segment)->slug()->toString()], ['name' => $segment, 'sort_order' => ($position + 1) * 10, 'is_active' => true]);
        }

        $noida = City::query()->where('slug', 'noida')->sole();

        foreach (self::PROPERTIES as $record) {
            $sector = MicroMarket::query()->firstOrCreate(
                ['city_id' => $noida->id, 'slug' => str($record['sector'])->slug()->toString()],
                ['name' => $record['sector']],
            );

            $property = Property::query()->firstOrCreate(['code' => $record['code']], [
                'city_id' => $noida->id,
                'micro_market_id' => $sector->id,
                'name' => $record['code'],
                'slug' => strtolower($record['code']),
                'operating_model' => OperatingModel::OwnedOperated,
                'operational_status' => PropertyStatus::Open,
            ]);

            $property->buildings()->firstOrCreate(['is_primary' => true], [
                'code' => $record['code'].'-MAIN',
                'address_line_1' => $record['code'],
                'locality' => $record['sector'],
                'city_id' => $noida->id,
                'state' => 'Uttar Pradesh',
            ]);

            $property->profile()->firstOrCreate([]);
        }
    }
}
