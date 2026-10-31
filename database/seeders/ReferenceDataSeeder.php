<?php

namespace Database\Seeders;

use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\City;
use App\Models\CustomerSegment;
use App\Models\MicroMarket;
use App\Models\Product;
use App\Models\Property;
use App\Models\SpaceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Only facts the owner has confirmed, so it can run on any environment and run again safely:
 * the five cities with their official names (OD-03, OD-16), the seven workspace categories (OD-07)
 * the eight customer segments (OD-04), and the four Noida properties (OD-08, OD-13), owned and
 * operated (OD-14), open because Noida is the operating city (OD-03). A property is on the website only
 * once its profile is approved and published, so the seeder publishes nothing. Nothing is recorded about which property offers which category, nor about
 * floors, capacity, prices or availability: staff enter those from real information. Each property
 * gets an empty, unpublished website profile for staff to write.
 *
 * Where the database has them, each category also gets the inventory modes it can be sold with
 * (BR-PRODCFG-2), and the initial space types of DATABASE_ERD §3.1 are added: both are rules, not facts
 * about any property.
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
     * @var list<array{key: string, name: string, slug: string, allowed_inventory_modes: list<string>}>
     */
    private const array PRODUCTS = [
        ['key' => 'private_office', 'name' => 'Private offices and cabins', 'slug' => 'private-cabins', 'allowed_inventory_modes' => ['exclusive']],
        ['key' => 'dedicated_desk', 'name' => 'Dedicated desks', 'slug' => 'dedicated-desks', 'allowed_inventory_modes' => ['per_seat', 'hybrid']],
        ['key' => 'hot_desk', 'name' => 'Hot desks', 'slug' => 'hot-desks', 'allowed_inventory_modes' => ['pooled', 'per_seat', 'hybrid']],
        ['key' => 'meeting_room', 'name' => 'Meeting rooms', 'slug' => 'meeting-rooms', 'allowed_inventory_modes' => ['exclusive']],
        ['key' => 'conference_room', 'name' => 'Conference and training rooms', 'slug' => 'conference-training-rooms', 'allowed_inventory_modes' => ['exclusive']],
        ['key' => 'virtual_office', 'name' => 'Virtual office', 'slug' => 'virtual-office', 'allowed_inventory_modes' => []],
        ['key' => 'managed_office', 'name' => 'Managed offices', 'slug' => 'managed-offices', 'allowed_inventory_modes' => ['exclusive']],
    ];

    /**
     * The initial space types (DATABASE_ERD §3.1): seed values, not facts about any property.
     *
     * @var list<array{key: string, name: string, default_inventory_mode: string, allowed_inventory_modes: list<string>, is_bookable_by_time: bool}>
     */
    private const array SPACE_TYPES = [
        ['key' => 'private_cabin', 'name' => 'Private cabin or office', 'default_inventory_mode' => 'exclusive', 'allowed_inventory_modes' => ['exclusive'], 'is_bookable_by_time' => false],
        ['key' => 'desk_zone_dedicated', 'name' => 'Dedicated desk zone', 'default_inventory_mode' => 'per_seat', 'allowed_inventory_modes' => ['per_seat', 'hybrid'], 'is_bookable_by_time' => false],
        ['key' => 'desk_zone_flexible', 'name' => 'Flexible seating zone', 'default_inventory_mode' => 'pooled', 'allowed_inventory_modes' => ['pooled', 'per_seat', 'hybrid'], 'is_bookable_by_time' => false],
        ['key' => 'meeting_room', 'name' => 'Meeting room', 'default_inventory_mode' => 'exclusive', 'allowed_inventory_modes' => ['exclusive'], 'is_bookable_by_time' => true],
        ['key' => 'conference_training_room', 'name' => 'Conference or training room', 'default_inventory_mode' => 'exclusive', 'allowed_inventory_modes' => ['exclusive'], 'is_bookable_by_time' => true],
        ['key' => 'office_suite', 'name' => 'Office suite or floor plate', 'default_inventory_mode' => 'exclusive', 'allowed_inventory_modes' => ['exclusive'], 'is_bookable_by_time' => false],
    ];

    /**
     * The categories each initial space type can be sold as (BR-PRODCFG-2): DATABASE_ERD §3.1's "typically
     * sellable as", plus the two combinations the approved SG-INV-1.3 synthetic workbook uses (D-35): a
     * flexible seating zone's assigned seats as dedicated desks (hybrid zones, OD-15), and a conference or
     * training room as a meeting room, which the ERD allows "if the owner allows" (owner decision, OD-153).
     * Proposed defaults that staff change on each category's form.
     *
     * @var array<string, list<string>>
     */
    private const array SPACE_TYPE_CATEGORIES = [
        'private_cabin' => ['private_office', 'managed_office'],
        'desk_zone_dedicated' => ['dedicated_desk'],
        'desk_zone_flexible' => ['hot_desk', 'dedicated_desk'],
        'meeting_room' => ['meeting_room'],
        'conference_training_room' => ['conference_room', 'meeting_room'],
        'office_suite' => ['managed_office', 'private_office'],
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
            Product::query()->firstOrCreate(['key' => $product['key']], [
                ...Arr::except($product, Product::hasInventoryModes() ? [] : ['allowed_inventory_modes']),
                'sort_order' => ($position + 1) * 10,
                'is_active' => true,
            ]);
        }

        if (SpaceType::isAvailable()) {
            foreach (self::SPACE_TYPES as $position => $type) {
                $spaceType = SpaceType::query()->firstOrCreate(['key' => $type['key']], [...$type, 'sort_order' => ($position + 1) * 10]);

                // Only a type no category accepts yet gets the defaults, so staff choices are never undone.
                if (Product::hasSpaceTypes() && ! $spaceType->products()->exists()) {
                    $spaceType->products()->attach(Product::query()->whereIn('key', self::SPACE_TYPE_CATEGORIES[$type['key']] ?? [])->pluck('id')->all());
                }
            }
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
