<?php

namespace Database\Seeders;

use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Enums\Inventory\SpaceStatus;
use App\Models\MediaAsset;
use App\Models\NavigationMenu;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Models\User;
use App\Services\Content\NavigationPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The spaces under Spaces in the website's menu (OD-177), for the business team to change from the back office
 * (Website → Spaces on the website), written through the real workflow: an author, a different approver, then a
 * publisher.
 *
 *   php artisan db:seed --class=SpaceListingSeeder
 *
 * For every location that may be listed and every space type: the inventory's own spaces of that type there, when
 * any is marked publicly listable and in use ("use the original spaces"); otherwise three example spaces, marked as
 * such, with no seats, prices or availability, each with a labelled stock photo where the media library has one
 * (EXAMPLE_PHOTOS: run it after DemoContentSeeder and HomeShowcaseSeeder). Then the main menu's Spaces drop-down becomes a tab per space type
 * (the owner's "dedicated desk and then show the space under it at what location"), keeping its plain links such as
 * "All workspace types"; its links to workspace types move out, since Solutions and the workspaces page carry them.
 *
 * Writes as the dummy Marketing Managers (DummyStaffSeeder, run first when they are missing). Safe to rerun:
 * unchanged content gets no new version, and content someone else published or is working on, or a menu someone
 * else published, is left alone (listed under "left_alone"). On a database without the space listing table it does
 * nothing. Refused in production.
 */
class SpaceListingSeeder extends Seeder
{
    /**
     * Example spaces per space type key: three names, a summary and features. Example text, not SmartGro's.
     *
     * @var array<string, array{tab: string, names: list<string>, summary: string, features: list<string>}>
     */
    public const array EXAMPLES = [
        'private_cabin' => [
            'tab' => 'Private cabins',
            'names' => ['Cabin C1', 'Cabin C2', 'Cabin C3'],
            'summary' => 'An example private cabin: a lockable room for a small team that wants a space of its own.',
            'features' => ['Lockable door', 'Room for a small team', 'Meeting rooms nearby'],
        ],
        'desk_zone_dedicated' => [
            'tab' => 'Dedicated desks',
            'names' => ['Desk zone D1', 'Desk zone D2', 'Desk zone D3'],
            'summary' => 'An example dedicated desk zone: a desk of your own in a shared workspace, kept for you.',
            'features' => ['A desk kept for you', 'Storage for your things', 'Shared kitchen and lounge'],
        ],
        'desk_zone_flexible' => [
            'tab' => 'Flexible seating',
            'names' => ['Open zone F1', 'Open zone F2', 'Open zone F3'],
            'summary' => 'An example flexible seating zone: take any open desk when you need one.',
            'features' => ['Any open desk', 'Suits hybrid teams', 'Shared kitchen and lounge'],
        ],
        'meeting_room' => [
            'tab' => 'Meeting rooms',
            'names' => ['Meeting room M1', 'Meeting room M2', 'Meeting room M3'],
            'summary' => 'An example meeting room for client meetings and team discussions.',
            'features' => ['Screen for presentations', 'Whiteboard', 'Set up for video calls'],
        ],
        'conference_training_room' => [
            'tab' => 'Conference rooms',
            'names' => ['Training room T1', 'Conference room T2', 'Board room T3'],
            'summary' => 'An example larger room for workshops, training and events.',
            'features' => ['Room for workshops and training', 'Screen and sound', 'Flexible layouts'],
        ],
        'office_suite' => [
            'tab' => 'Office suites',
            'names' => ['Office suite S1', 'Office suite S2', 'Office suite S3'],
            'summary' => 'An example office suite: a self-contained office for a growing team or company.',
            'features' => ['Self-contained office', 'Room for a whole team', 'Set up for your company'],
        ],
    ];

    /**
     * Stock photos for the example spaces (OD-177 e), by space type key, one per example in turn: CC0 photos already in
     * the media library from DemoContentSeeder (OD-176) and HomeShowcaseSeeder (OD-169), as [seeder, image key]. A
     * photo that is not in the library is left out; the page labels each as "Stock photo, not a SmartGro location".
     *
     * @var array<string, list<array{0: class-string, 1: string}>>
     */
    public const array EXAMPLE_PHOTOS = [
        'private_cabin' => [[DemoContentSeeder::class, 'cabins']],
        'desk_zone_dedicated' => [[DemoContentSeeder::class, 'desk']],
        'desk_zone_flexible' => [[DemoContentSeeder::class, 'shared_table'], [HomeShowcaseSeeder::class, 'loft_lounge']],
        'meeting_room' => [[DemoContentSeeder::class, 'whiteboards'], [HomeShowcaseSeeder::class, 'meeting_room']],
        'conference_training_room' => [[DemoContentSeeder::class, 'training'], [DemoContentSeeder::class, 'boardroom']],
        'office_suite' => [[HomeShowcaseSeeder::class, 'loft_lounge'], [DemoContentSeeder::class, 'cabins']],
    ];

    /**
     * The example photos found in the media library during this run, by space type key.
     *
     * @var array<string, list<int>>
     */
    private array $examplePhotoIds = [];

    public function run(): void
    {
        $marketing = fn (string $key): ?User => User::query()->where('email', DummyStaffSeeder::email($key))->first();

        if ($marketing('marketing') === null || $marketing('marketing2') === null) {
            $this->call(DummyStaffSeeder::class);
        }

        [$author, $approver] = [$marketing('marketing'), $marketing('marketing2')];

        if (! $author->is_active || ! $approver->is_active) {
            throw new RuntimeException('The dummy Marketing Managers write and approve the sample spaces: activate both first.');
        }

        $this->command?->info('Spaces on the website: '.json_encode($this->seedWith($author, $approver, $author), JSON_UNESCAPED_SLASHES));
    }

    /**
     * Write the spaces and the menu as these staff; the approver must be someone other than the author and the publisher.
     *
     * @return array{skipped: bool, from_inventory: int, examples: int, menu: string, left_alone: list<string>}
     */
    public function seedWith(User $author, User $approver, User $publisher): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample spaces are for local databases only.');
        }

        if (! SpaceListing::isAvailable()) {
            return ['skipped' => true, 'from_inventory' => 0, 'examples' => 0, 'menu' => 'unchanged', 'left_alone' => []];
        }

        // Allowed for this run even when `.env` refuses sample content for the owner's server (OD-152).
        $sampleContent = config('backoffice.sample_content');
        config(['backoffice.sample_content' => true]);

        try {
            return $this->write(['author' => $author, 'approver' => $approver, 'publisher' => $publisher]);
        } finally {
            config(['backoffice.sample_content' => $sampleContent]);
        }
    }

    /**
     * @param  array{author: User, approver: User, publisher: User}  $staff
     * @return array{skipped: bool, from_inventory: int, examples: int, menu: string, left_alone: list<string>}
     */
    private function write(array $staff): array
    {
        $leftAlone = [];
        $counts = ['from_inventory' => 0, 'examples' => 0];

        // As HomeShowcaseSeeder: content this seeder published and nobody has changed gets a new version only when its
        // words differ; content someone else published, or with a version in progress, is left alone.
        $publish = function (Model $content, array $payload, string $name) use ($staff, &$leftAlone): void {
            $published = $content->publishedRevision;

            // A space someone switched off stays off.
            if ($content->revisions()->whereIn('state', RevisionState::open())->exists() || $content->getAttribute('status') === 'unpublished'
                || ($published !== null && $published->author_id !== $staff['author']->id && (bool) $published->author?->is_active)) {
                $leftAlone[] = $name;

                return;
            }

            if ($published !== null && $published->payload == $content->prepareRevisionPayload($payload, forSubmission: true)) {
                return;
            }

            $draft = app(CreateDraft::class)->handle($content, $staff['author'], $payload);
            app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $staff['author']), $staff['approver']), $staff['publisher']);
        };

        // A listing found by an address it has had in any version, so one the team has since renamed is still recognised.
        $find = fn (string $slug): SpaceListing => SpaceListing::query()->where('slug', $slug)->first()
            ?? SpaceListing::query()->whereHas('revisions', fn ($revisions) => $revisions->where('payload->slug', $slug))->first()
            ?? SpaceListing::query()->forceCreate(['status' => 'draft']);

        $properties = Property::query()->with(['city', 'microMarket'])->get()
            ->filter(fn (Property $property): bool => $property->operational_status->allowsListing($property->opens_on !== null))
            ->sortBy('code', SORT_NATURAL)->values();
        $types = SpaceType::query()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($properties as $property) {
            foreach ($types as $type) {
                $originals = Space::isAvailable() ? Space::query()
                    ->where('property_id', $property->id)->where('space_type_id', $type->id)
                    ->where('is_publicly_listable', true)->where('operational_status', SpaceStatus::Active->value)
                    ->orderBy('code')->get() : collect();

                if ($originals->isNotEmpty()) {
                    foreach ($originals->values() as $position => $space) {
                        $name = $space->name ?? $space->code;
                        $slug = Str::slug($property->code.' '.$space->code);
                        $publish($find($slug), [
                            'property_id' => $property->id, 'space_type_id' => $type->id, 'space_id' => $space->id,
                            'name' => Str::limit($name, 80, ''), 'slug' => $slug,
                            'summary' => Str::limit($type->name.' at SmartGro '.$property->name.'.', 200, ''),
                            'details' => null, 'seats' => $space->capacity_seats ?: null, 'features' => [],
                            'is_sample' => false, 'sort_order' => ($position + 1) * 10,
                        ], 'SpaceListing "'.$slug.'"');
                        $counts['from_inventory']++;
                    }

                    continue;
                }

                $example = self::EXAMPLES[$type->key] ?? ['names' => [$type->name.' 1', $type->name.' 2', $type->name.' 3'], 'summary' => 'An example '.Str::lower($type->name).'.', 'features' => []];
                $photos = $this->examplePhotos($type->key);

                foreach ($example['names'] as $position => $name) {
                    $slug = Str::slug($property->code.' '.$name);
                    $publish($find($slug), [
                        'property_id' => $property->id, 'space_type_id' => $type->id, 'space_id' => null,
                        'name' => $name, 'slug' => $slug,
                        'summary' => $example['summary'],
                        'details' => 'This is an example space, so that the website can show how a space\'s page looks. SmartGro will replace it with the real details: its size, what is included and when it is available.'
                            ."\n\n".'Ask SmartGro about this kind of space at SmartGro '.$property->name.'. The team will reply with what is actually available there.',
                        'seats' => null, 'features' => $example['features'],
                        ...(SpaceListing::hasPhotos() ? ['media_asset_id' => $photos === [] ? null : $photos[$position % count($photos)]] : []),
                        'is_sample' => true, 'sort_order' => ($position + 1) * 10,
                    ], 'SpaceListing "'.$slug.'"');
                    $counts['examples']++;
                }
            }
        }

        return ['skipped' => false, ...$counts, 'menu' => $this->menu($types->all(), $staff, $leftAlone), 'left_alone' => $leftAlone];
    }

    /**
     * The IDs of the stock photos in the media library for a space type's examples, in order.
     *
     * @return list<int>
     */
    private function examplePhotos(string $typeKey): array
    {
        return $this->examplePhotoIds[$typeKey] ??= collect(self::EXAMPLE_PHOTOS[$typeKey] ?? [])
            ->map(fn (array $photo): ?int => MediaAsset::query()->where('origin', MediaOrigin::LicensedStock)->where('title', $photo[0]::IMAGES[$photo[1]]['title'])->value('id'))
            ->filter()->values()->all();
    }

    /**
     * Turns the main menu's Spaces drop-down into a tab per space type, keeping its plain links, when the live menu
     * is this seeder's (or SampleContentSeeder's) and nobody is working on it.
     *
     * @param  list<SpaceType>  $types
     * @param  array{author: User, approver: User, publisher: User}  $staff
     * @param  list<string>  $leftAlone
     */
    private function menu(array $types, array $staff, array &$leftAlone): string
    {
        $menu = NavigationMenu::query()->where('handle', 'main')->first();
        $published = $menu?->publishedRevision;

        if ($menu === null || $published === null) {
            return 'not published';
        }

        if ($menu->revisions()->whereIn('state', RevisionState::open())->exists() || ($published->author_id !== $staff['author']->id && (bool) $published->author?->is_active)) {
            $leftAlone[] = 'NavigationMenu "main"';

            return 'left alone';
        }

        $payload = $menu->toRevisionPayload();
        $index = collect($payload['items'])->search(fn (array $item): bool => $item['type'] === NavigationPayload::GROUP && $item['label'] === 'Spaces');

        if ($index === false) {
            return 'no Spaces drop-down';
        }

        $payload['items'][$index]['children'] = [
            ...array_values(array_filter($payload['items'][$index]['children'], fn (array $child): bool => ! in_array($child['type'], [NavigationPayload::GROUP, NavigationPayload::CATEGORY, NavigationPayload::SPACE_TYPE], true))),
            ...array_map(fn (SpaceType $type): array => [
                'label' => self::EXAMPLES[$type->key]['tab'] ?? Str::limit($type->name, 40, ''),
                'type' => NavigationPayload::SPACE_TYPE, 'page_id' => null, 'section' => null, 'property_id' => null, 'product_id' => null, 'url' => null,
                'space_type_id' => $type->id, 'description' => null, 'icon' => null,
            ], $types),
        ];

        if ($published->payload == $menu->prepareRevisionPayload($payload, forSubmission: true)) {
            return 'unchanged';
        }

        $draft = app(CreateDraft::class)->handle($menu, $staff['author'], $payload);
        app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $staff['author']), $staff['approver']), $staff['publisher']);

        return 'published';
    }
}
