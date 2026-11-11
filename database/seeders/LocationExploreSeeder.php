<?php

namespace Database\Seeders;

use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UpdateDraft;
use App\Actions\Media\CreateMediaAsset;
use App\Actions\Twin\CreateTwinModel;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Enums\Twin\CaptureType;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\TwinModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * The demo of each location's 3D walkthrough and floor plans (OD-181), for the owner to see the "Explore" section of the
 * location pages working and to replace it later from the back office:
 *
 * - a 3D model per location, made from one of two invented office floors (ExampleOfficeModel), uploaded through the
 *   real checks as a **conceptual model**, so the page always shows "Conceptual illustration — not a scan of this
 *   location" with it, and titled as an example;
 * - two example floor plans drawn from the same floors (`media/demo/floor-plans/`), in the media library as renders,
 *   which the page labels "Example floor plan — not SmartGro's layout";
 * - each published location profile given that model, five guided views and both plans, through the workflow: the
 *   Marketing Manager writes and publishes, the second Marketing Manager approves.
 *
 *   php artisan db:seed --class=LocationExploreSeeder
 *
 * Needs the columns of the OD-181 migration, the dummy staff (DummyStaffSeeder, run first when missing) and published
 * location profiles (DemoContentSeeder); a location whose profile is not published is skipped. Sample content is
 * allowed for this run only; production is refused. Safe to rerun: models and plans are found by their titles,
 * unchanged profiles get no new version, and a profile someone else published or is working on is left alone.
 */
class LocationExploreSeeder extends Seeder
{
    /**
     * The example floor each location's model shows, by property code; any other location takes them in turn.
     *
     * @var array<string, string>
     */
    public const array MODEL_FLOORS = [
        'A-70' => ExampleOfficeModel::OPEN_PLAN,
        'A-71' => ExampleOfficeModel::MEETING_FLOOR,
        'A-23' => ExampleOfficeModel::OPEN_PLAN,
        'H-105' => ExampleOfficeModel::MEETING_FLOOR,
    ];

    /**
     * The example floor plans, by floor: the file in `media/demo/floor-plans/` and its media library details.
     *
     * @var array<string, array{file: string, title: string, alt: string}>
     */
    public const array FLOOR_PLANS = [
        ExampleOfficeModel::OPEN_PLAN => [
            'file' => 'open-plan.png',
            'title' => 'Example floor plan: open plan with private cabins (demo)',
            'alt' => 'Example floor plan, not a SmartGro layout: 4 private cabins, 2 phone booths, a meeting room for 8, 24 open desks, reception and a pantry.',
        ],
        ExampleOfficeModel::MEETING_FLOOR => [
            'file' => 'meeting-floor.png',
            'title' => 'Example floor plan: meeting and training floor (demo)',
            'alt' => 'Example floor plan, not a SmartGro layout: a training room for 16, a board room for 10, 2 meeting rooms for 4, 12 desks, a lounge and a pantry.',
        ],
    ];

    public function run(): void
    {
        $staff = fn (string $key): ?User => User::query()->where('email', DummyStaffSeeder::email($key))->first();

        if ($staff('marketing') === null || $staff('marketing2') === null) {
            $this->call(DummyStaffSeeder::class);
        }

        $this->command?->info('Location 3D walkthroughs and floor plans: '.json_encode($this->seedWith([
            'author' => $staff('marketing'),
            'approver' => $staff('marketing2'),
        ]), JSON_UNESCAPED_SLASHES));
    }

    /**
     * Write the demo as these staff: the author uploads, writes and publishes; the approver is someone else.
     *
     * @param  array{author: User, approver: User}  $staff
     * @return array{models: int, floor_plans: int, locations: int, skipped: list<string>, left_alone: list<string>}
     */
    public function seedWith(array $staff): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The 3D walkthrough and floor plan demo is for local databases only.');
        }

        if (! PropertyProfile::hasExplore()) {
            throw new RuntimeException('Location profiles cannot hold a 3D walkthrough or floor plans on this database yet: run the OD-181 migration first.');
        }

        foreach ($staff as $role => $user) {
            if (! $user->is_active) {
                throw new RuntimeException("The dummy account for {$role} writes the demo: activate it first.");
            }
        }

        // Allowed for this run even when `.env` refuses sample content for the owner's server (OD-152).
        $sampleContent = config('backoffice.sample_content');
        config(['backoffice.sample_content' => true]);

        try {
            return $this->write($staff);
        } finally {
            config(['backoffice.sample_content' => $sampleContent]);
        }
    }

    /**
     * @param  array{author: User, approver: User}  $staff
     * @return array{models: int, floor_plans: int, locations: int, skipped: list<string>, left_alone: list<string>}
     */
    private function write(array $staff): array
    {
        $result = ['models' => 0, 'floor_plans' => 0, 'locations' => 0, 'skipped' => [], 'left_alone' => []];
        $plans = [];

        foreach (self::FLOOR_PLANS as $floor => $plan) {
            [$plans[$floor], $created] = $this->floorPlan($plan, $staff['author']);
            $result['floor_plans'] += (int) $created;
        }

        foreach (Property::query()->with('profile')->orderBy('id')->get()->values() as $position => $property) {
            $profile = $property->profile;

            if ($profile === null || ! $profile->isPublished()) {
                $result['skipped'][] = $property->code;

                continue;
            }

            if ($this->isLeftAlone($profile, $staff['author'])) {
                $result['left_alone'][] = 'PropertyProfile '.$property->code;

                continue;
            }

            $floor = self::MODEL_FLOORS[$property->code] ?? ExampleOfficeModel::FLOORS[$position % 2];
            $otherFloor = $floor === ExampleOfficeModel::OPEN_PLAN ? ExampleOfficeModel::MEETING_FLOOR : ExampleOfficeModel::OPEN_PLAN;
            [$model, $created] = $this->model($property, $floor, $staff['author']);
            $result['models'] += (int) $created;

            $explore = [
                'twin_model_id' => $model->getKey(),
                'twin_tour_stops' => array_map(
                    fn (array $view, int $index): array => ['camera' => 'SG_CAM_'.($index + 1), 'title' => $view['title']],
                    ExampleOfficeModel::floor($floor)['views'],
                    array_keys(ExampleOfficeModel::floor($floor)['views']),
                ),
                'floor_plans' => [
                    ['label' => 'Level 1 (example)', 'media_asset_id' => $plans[$floor]->getKey(), 'note' => 'Example: '.ExampleOfficeModel::floor($floor)['summary'].' The 3D model shows this floor.', 'is_example' => true],
                    ['label' => 'Level 2 (example)', 'media_asset_id' => $plans[$otherFloor]->getKey(), 'note' => 'Example: '.ExampleOfficeModel::floor($otherFloor)['summary'], 'is_example' => true],
                ],
            ];

            $result['locations'] += (int) $this->publish($profile, [...$profile->toRevisionPayload(), ...$explore], $staff);
        }

        return $result;
    }

    /**
     * As DemoContentSeeder: a profile someone else published, or has a version in progress of, is left alone; a draft
     * this author started alone is carried on.
     */
    private function isLeftAlone(PropertyProfile $profile, User $author): bool
    {
        $open = $profile->openRevision();
        $published = $profile->publishedRevision;

        return ($open !== null && ($open->state !== RevisionState::Draft || $open->author_id !== $author->id || array_diff($open->editor_ids ?? [], [$author->id]) !== []))
            || ($open === null && $published !== null && $published->author_id !== $author->id && (bool) $published->author?->is_active);
    }

    /**
     * Writes, approves and publishes a version, only when its words differ from the live one.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{author: User, approver: User}  $staff
     * @return bool whether a version was published
     */
    private function publish(PropertyProfile $profile, array $payload, array $staff): bool
    {
        $open = $profile->openRevision();
        $published = $profile->publishedRevision;

        if ($open === null && $published !== null && $published->payload == $profile->prepareRevisionPayload($payload, forSubmission: true)) {
            return false;
        }

        $draft = $open === null
            ? app(CreateDraft::class)->handle($profile, $staff['author'], $payload)
            : app(UpdateDraft::class)->handle($open, $staff['author'], $payload);
        app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $staff['author']), $staff['approver']), $staff['author']);

        return true;
    }

    /**
     * The location's example model of this floor, uploaded through the real checks the first time.
     *
     * @return array{0: TwinModel, 1: bool} the model, and whether it was uploaded now
     */
    private function model(Property $property, string $floor, User $uploader): array
    {
        $title = 'Example 3D model: '.mb_strtolower(ExampleOfficeModel::floor($floor)['title']).' (demo, not SmartGro\'s layout)';
        $existing = TwinModel::query()->where('property_id', $property->getKey())->where('title', $title)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $path = tempnam(sys_get_temp_dir(), 'smartgro-demo-');
        file_put_contents($path, ExampleOfficeModel::glb($floor));

        try {
            return [app(CreateTwinModel::class)->handle($uploader, new UploadedFile($path, "example-{$floor}.glb", 'model/gltf-binary', null, true), [
                'property_id' => $property->getKey(),
                'title' => $title,
                'capture_type' => CaptureType::ConceptualModel->value,
                'captured_by' => 'Generated for the website demo (OD-181)',
            ]), true];
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * The example floor plan in the media library, added the first time from a copy of the repository's file (the
     * media library moves the file it is given).
     *
     * @param  array{file: string, title: string, alt: string}  $plan
     * @return array{0: MediaAsset, 1: bool} the image, and whether it was added now
     */
    private function floorPlan(array $plan, User $uploader): array
    {
        $existing = MediaAsset::query()->where('title', $plan['title'])->where('origin', MediaOrigin::Render)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $source = database_path('seeders/media/demo/floor-plans/'.$plan['file']);

        if (! is_file($source)) {
            throw new RuntimeException("The demo floor plan {$plan['file']} is missing.");
        }

        $copy = tempnam(sys_get_temp_dir(), 'smartgro-demo-');
        copy($source, $copy);

        try {
            return [app(CreateMediaAsset::class)->handle($uploader, new UploadedFile($copy, $plan['file'], null, null, true), [
                'title' => $plan['title'],
                'alt_text' => $plan['alt'],
                'origin' => MediaOrigin::Render->value,
                'is_decorative' => false,
                'credit_line' => 'Example drawing for the website demo, not a SmartGro floor plan',
                'usage_restrictions' => 'Example floor plan, not SmartGro\'s layout (OD-181). Replace with SmartGro\'s own plan when available.',
                'not_from_old_website' => true,
            ]), true];
        } finally {
            if (is_file($copy)) {
                unlink($copy);
            }
        }
    }
}
