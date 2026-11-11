<?php

namespace Database\Seeders;

use App\Actions\Booking\SaveTourRule;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UpdateDraft;
use App\Actions\Media\CreateMediaAsset;
use App\Actions\Properties\SaveProperty;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Models\CaseStudy;
use App\Models\MediaAsset;
use App\Models\NavigationMenu;
use App\Models\Offering;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Demo details for every part of the website that is still empty (OD-176), for the owner to see the whole
 * website working and to change everything later from the back office:
 *
 * - placeholder contact details and social links that reach nobody (`.example` email, an all-zeros phone);
 * - a sample privacy notice at /legal/privacy (the sample layout: marked "Sample content", never indexed),
 *   which opens the enquiry and tour forms, and example tour hours at each location;
 * - every active workspace type offered at each of the four locations, with the location pages and the
 *   workspace pages published with example text and labelled stock photos;
 * - two example testimonials inside two example case studies, by fictional people and companies.
 *
 *   php artisan db:seed --class=DemoContentSeeder
 *
 * writes through the real actions and workflow as the dummy staff (DummyStaffSeeder, run first when they are
 * missing): the Marketing Managers write, approve and publish, the Operations Manager records the workspace
 * types and the Property Manager the tour hours. Everything invented says it is an example. Stock photos are
 * CC0, recorded with their source, and labelled "Stock photo, not a SmartGro location" wherever they appear.
 * Sample content is allowed for this run only; production is refused. Safe to rerun: unchanged content gets
 * no new version, and content someone else published or is working on is left alone (listed under
 * "left_alone"), as are locations that already have tour hours.
 */
class DemoContentSeeder extends Seeder
{
    /**
     * The new stock photos, by key: the file and its media library details. The home banners' photos
     * (HomeShowcaseSeeder::IMAGES) are used too.
     *
     * @var array<string, array{file: string, title: string, alt: string, holder: string, page: string}>
     */
    public const array IMAGES = [
        'cabins' => ['file' => 'glass-walled-office-cabins.jpg', 'title' => 'Demo: glass-walled office cabins (stock)', 'alt' => 'A row of glass-walled office cabins along a corridor', 'holder' => 'Matt Moloney', 'page' => 'office-interior-NSQTXCLWQ5'],
        'desk' => ['file' => 'single-desk-by-a-white-wall.jpg', 'title' => 'Demo: single desk by a white wall (stock)', 'alt' => 'A simple white desk and chair against a white wall, with a plant and a framed print', 'holder' => 'Bench Accounting', 'page' => 'office-desk-N7FUTW0RY5'],
        'shared_table' => ['file' => 'shared-work-table-in-a-brick-loft.jpg', 'title' => 'Demo: shared work table in a brick loft (stock)', 'alt' => 'A long shared work table with white chairs in a loft with brick walls and tall windows', 'holder' => 'Snappa.io', 'page' => 'office-desk-OVWKT15VV0'],
        'whiteboards' => ['file' => 'meeting-room-with-whiteboards.jpg', 'title' => 'Demo: meeting room with whiteboards (stock)', 'alt' => 'A bright meeting room with a white table, dark chairs, whiteboards and a wall screen', 'holder' => 'Breather', 'page' => 'interior-design-70ZAX0HRQQ'],
        'boardroom' => ['file' => 'long-boardroom-table-by-windows.jpg', 'title' => 'Demo: long boardroom table by windows (stock)', 'alt' => 'A long boardroom table with grey chairs beside a wall of windows', 'holder' => 'Benjamin Child', 'page' => 'table-chairs-7OPWOSQFXS'],
        'lobby' => ['file' => 'quiet-lobby-with-a-bench.jpg', 'title' => 'Demo: quiet lobby with a bench (stock)', 'alt' => 'A quiet lobby with a black leather bench in soft daylight', 'holder' => 'Patrick McMahon', 'page' => 'sunlight-couch-NB6ISAF275'],
        'training' => ['file' => 'training-room-with-long-table.jpg', 'title' => 'Demo: training room with a long table (stock)', 'alt' => 'A white training room with a long table and grey upholstered chairs', 'holder' => 'Breather', 'page' => 'interior-design-N3DP51WC97'],
    ];

    /**
     * Example lists of what each workspace type includes, and its photos (main photo first).
     *
     * @var array<string, array{included: list<string>, photos: list<string>}>
     */
    private const array WORKSPACES = [
        'private_office' => ['included' => ['A lockable cabin for your team', 'Furniture and storage', 'Wi-Fi', 'Use of shared meeting rooms (ask SmartGro)'], 'photos' => ['cabins', 'lounge']],
        'dedicated_desk' => ['included' => ['A desk kept for you', 'A lockable drawer', 'Wi-Fi', 'Use of the shared areas'], 'photos' => ['desk', 'shared_table']],
        'hot_desk' => ['included' => ['Any open desk when you arrive', 'Wi-Fi', 'Use of the shared areas'], 'photos' => ['shared_table', 'loft_lounge']],
        'meeting_room' => ['included' => ['A screen for presentations', 'Whiteboard', 'Wi-Fi'], 'photos' => ['whiteboards', 'meeting_room']],
        'conference_room' => ['included' => ['Seating for workshops and training', 'A screen for presentations', 'Wi-Fi'], 'photos' => ['boardroom', 'training']],
        'virtual_office' => ['included' => ['A business address for your company', 'Mail handling (ask SmartGro)', 'Meeting rooms when you need one (ask SmartGro)'], 'photos' => ['lobby', 'lounge']],
        'managed_office' => ['included' => ['Space set up for your organisation', 'Run day to day by SmartGro', 'A layout agreed with your team'], 'photos' => ['loft_lounge', 'cabins']],
    ];

    /**
     * Each location's photos, by property code (main photo first).
     *
     * @var array<string, list<string>>
     */
    private const array LOCATION_PHOTOS = [
        'A-70' => ['loft_lounge', 'cabins', 'whiteboards', 'lobby'],
        'A-71' => ['lounge', 'shared_table', 'boardroom', 'desk'],
        'A-23' => ['meeting_room', 'loft_lounge', 'cabins', 'lobby'],
        'H-105' => ['shared_table', 'lounge', 'whiteboards', 'training'],
    ];

    /**
     * Example tour hours at every location: weekday => [from, to], with 30-minute tours, one at a time.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const array TOUR_HOURS = [1 => ['10:00', '18:00'], 2 => ['10:00', '18:00'], 3 => ['10:00', '18:00'], 4 => ['10:00', '18:00'], 5 => ['10:00', '18:00'], 6 => ['10:00', '14:00']];

    private const string EXAMPLE_CONSENT = 'EXAMPLE (OD-176): fictional, no real person or company. Replace before launch.';

    public function run(): void
    {
        $staff = fn (string $key): ?User => User::query()->where('email', DummyStaffSeeder::email($key))->first();

        if (collect(['marketing', 'marketing2', 'operations', 'property'])->contains(fn (string $key): bool => $staff($key) === null)) {
            $this->call(DummyStaffSeeder::class);
        }

        $this->command?->info('Demo content: '.json_encode($this->seedWith([
            'author' => $staff('marketing'),
            'approver' => $staff('marketing2'),
            'operations' => $staff('operations'),
            'property' => $staff('property'),
        ]), JSON_UNESCAPED_SLASHES));
    }

    /**
     * Write the demo content as these staff: the author also publishes, the approver is someone else.
     *
     * @param  array{author: User, approver: User, operations: User, property: User}  $staff
     * @return array{images: int, settings: list<string>, pages: list<string>, offerings: int, locations: int, workspaces: int, tour_hours: int, testimonials: int, case_studies: int, left_alone: list<string>}
     */
    public function seedWith(array $staff): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo content is for local databases only.');
        }

        foreach ($staff as $role => $user) {
            if (! $user->is_active) {
                throw new RuntimeException("The dummy account for {$role} writes the demo content: activate it first.");
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
     * @param  array{author: User, approver: User, operations: User, property: User}  $staff
     * @return array{images: int, settings: list<string>, pages: list<string>, offerings: int, locations: int, workspaces: int, tour_hours: int, testimonials: int, case_studies: int, left_alone: list<string>}
     */
    private function write(array $staff): array
    {
        $leftAlone = [];
        $label = fn (Model $content): string => class_basename($content).' '.($content->slug ?? $content->handle ?? $content->getKey());

        // As SampleContentSeeder: a new version only when the words differ; content someone else published, or with a
        // version in progress, is left alone. A draft this seeder's author started alone (SampleContentSeeder's
        // location drafts) is carried on instead of starting another.
        $publish = function (Model $content, array $payload) use ($staff, $label, &$leftAlone): bool {
            $open = $content->openRevision();
            $published = $content->publishedRevision;

            if ($open !== null && ($open->state !== RevisionState::Draft || $open->author_id !== $staff['author']->id || array_diff($open->editor_ids ?? [], [$staff['author']->id]) !== [])) {
                $leftAlone[] = $label($content);

                return false;
            }

            if ($open === null && $published !== null && $published->author_id !== $staff['author']->id && (bool) $published->author?->is_active) {
                $leftAlone[] = $label($content);

                return false;
            }

            if ($open === null && $published !== null && $published->payload == $content->prepareRevisionPayload($payload, forSubmission: true)) {
                return false;
            }

            $draft = $open === null
                ? app(CreateDraft::class)->handle($content, $staff['author'], $payload)
                : app(UpdateDraft::class)->handle($open, $staff['author'], $payload);
            app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $staff['author']), $staff['approver']), $staff['author']);

            return true;
        };

        // Content found by a field, live or only in a version, or a new draft record.
        $find = fn (string $model, string $field, string $value): Model => $model::query()->where($field, $value)->first()
            ?? $model::query()->whereHas('revisions', fn ($revisions) => $revisions->where("payload->{$field}", $value))->first()
            ?? $model::query()->forceCreate(['status' => 'draft']);

        $images = $this->images($staff['author']);
        $image = fn (string $key, ?string $caption = 'Stock photo, not a SmartGro location'): array => ['type' => 'image', 'data' => ['media_asset_id' => $images[$key]->id, 'alt' => $images[$key]->alt_text, 'decorative' => false, 'caption' => $caption]];

        // Contact details and social links that reach nobody: a reserved `.example` address, an all-zeros number,
        // and social addresses no account uses.
        $settings = [];
        $contact = [
            'phone' => '+91 00000 00000',
            'email' => 'hello@smartgro.example',
            'whatsapp' => '+910000000000',
            'address' => 'Example address for SmartGro to replace, Noida, Uttar Pradesh',
            'opening_hours' => 'Example: Monday to Saturday, 9:00 to 19:00',
        ];
        $social = [
            'linkedin' => 'https://www.linkedin.com/company/smartgro-example-replace-me',
            'instagram' => 'https://www.instagram.com/smartgro.example.replace.me',
            'facebook' => 'https://www.facebook.com/smartgro.example.replace.me',
            'x' => 'https://x.com/smartgro_example_replace_me',
            'youtube' => 'https://www.youtube.com/@smartgro-example-replace-me',
        ];
        foreach (['contact' => $contact, 'social' => $social] as $handle => $values) {
            $publish(SiteSetting::query()->where('handle', $handle)->sole(), $values);
            $settings[] = $handle;
        }

        // A sample privacy notice: it opens the enquiry and tour forms (EnquiryForm, TourForm) until SmartGro publishes
        // its own, and says so at the top. What it says the forms collect is what this website records.
        $text = fn (string $heading, string $body): array => ['type' => 'text', 'data' => ['heading' => $heading, 'body' => $body]];
        $privacy = $find(Page::class, 'slug', 'legal/privacy');
        $publish($privacy, ['title' => 'Privacy notice (sample)', 'slug' => 'legal/privacy', 'template' => 'sample', 'blocks' => [
            $text('This is a sample', '<p><strong>This is a sample privacy notice, not SmartGro\'s own.</strong> It lets you try the enquiry and tour forms on this website before launch. SmartGro replaces it with its own notice, reviewed by its legal adviser, before the website goes live.</p>'),
            $text('What the forms record', '<p>When you send an enquiry or ask for a tour, the website records what you enter: your name, email address and phone number, your company if you give it, the workspace and location you ask about, the tour time you choose and your message. It also records when you sent it and which version of this notice you were shown.</p>'),
            $text('Why (example)', '<p>Example text: to reply to you, to arrange a tour you asked for, and to keep a record of what was agreed.</p>'),
            $text('How long it is kept (example)', '<p>Example text: SmartGro sets how long enquiries and tour requests are kept, and deletes them after that.</p>'),
            $text('Your choices (example)', '<p>Example text: you can ask SmartGro to show, correct or delete what it holds about you, using the contact details on this website.</p>'),
        ]]);
        $publish(NavigationMenu::query()->where('handle', 'legal')->sole(), ['items' => [['label' => 'Privacy', 'type' => 'page', 'page_id' => $privacy->id]]]);

        // Every active workspace type at every location (the Operations Manager's choice in the property screen); a
        // location keeps the types it already has.
        $products = Product::query()->shown()->get();
        $properties = Property::query()->with(['primaryBuilding', 'microMarket', 'city'])->orderBy('id')->get();
        $offerings = 0;
        foreach ($properties as $property) {
            $current = $property->products()->wherePivot('is_active', true)->pluck('products.id')->all();
            $wanted = array_values(array_unique([...$current, ...$products->modelKeys()]));

            if (array_diff($wanted, $current) === []) {
                continue;
            }

            app(SaveProperty::class)->handle($staff['operations'], $property, [
                ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id', 'operating_hours']),
                'opens_on' => $property->opens_on?->toDateString(),
                'operating_model' => $property->operating_model->value,
                'operational_status' => $property->operational_status->value,
                'building' => $property->primaryBuilding?->only(['code', 'name', 'address_line_1', 'address_line_2', 'locality', 'postal_code', 'state', 'latitude', 'longitude']) ?? [],
                'product_ids' => $wanted,
            ]);
            $offerings += count(array_diff($wanted, $current));
        }

        // The location pages and their workspace pages, with example text and labelled stock photos.
        $catalogue = app(WorkspaceCatalogue::class);
        $photoIds = fn (array $keys): array => array_map(fn (string $key): int => $images[$key]->id, $keys);
        $locations = 0;
        $workspaces = 0;
        foreach ($properties as $property) {
            $property->refresh();
            $where = trim(($property->microMarket?->name ?? '').', '.$property->city->name, ', ');
            $photos = $photoIds(self::LOCATION_PHOTOS[$property->code] ?? ['loft_lounge', 'lounge']);

            $profile = $property->profile()->firstOrCreate([]);
            $locations += (int) $publish($profile, [
                'headline' => "Workspaces at SmartGro {$property->name}, {$where}",
                'summary' => "Example text for SmartGro to replace: SmartGro {$property->name} in {$where} offers private cabins, desks, meeting rooms, virtual offices and managed offices. Describe what it is like to work here and who it suits. Nothing here is confirmed SmartGro information.",
                'getting_there' => "Example text: describe how to reach SmartGro {$property->name} by metro, bus and car, and where to enter the building.",
                'nearest_metro' => null,
                'metro_walk_minutes' => null,
                'buses' => null,
                'parking' => 'Example: ask SmartGro about parking for members and visitors.',
                'accessibility' => 'Example: ask SmartGro about step-free access before your visit.',
                'phone' => null,
                'whatsapp' => null,
                'email' => null,
                'hero_asset_id' => $photos[0],
                'gallery_asset_ids' => array_slice($photos, 1),
                'seo_title' => null,
                'seo_description' => null,
                // The 3D walkthrough and floor plans stay as they are (LocationExploreSeeder, OD-181).
                ...(PropertyProfile::hasExplore() ? $profile->only(PropertyProfile::EXPLORE_FIELDS) : []),
            ]);

            foreach ($property->offerings()->with(['product', 'profile'])->where('is_active', true)->get() as $offering) {
                /** @var Offering $offering */
                $workspace = self::WORKSPACES[$offering->product->key] ?? ['included' => [], 'photos' => ['loft_lounge']];
                $offeringPhotos = $photoIds($workspace['photos']);

                $workspaces += (int) $publish($offering->profile()->firstOrCreate([]), [
                    'headline' => "{$offering->product->name} at SmartGro {$property->name}",
                    'description' => 'Example text for SmartGro to replace: '.($catalogue->categoryCopy($offering->product)['summary'] ?? $offering->product->name.'.')." Describe {$offering->product->name} at SmartGro {$property->name} in {$where}. The list below is an example too. SmartGro shares prices and availability when you get in touch.",
                    'included' => $workspace['included'],
                    'not_included' => [],
                    'hero_asset_id' => $offeringPhotos[0],
                    'gallery_asset_ids' => array_slice($offeringPhotos, 1),
                    'seo_title' => null,
                    'seo_description' => null,
                ]);
            }
        }

        // Example tour hours, set by the Property Manager; a location that already has tour hours keeps them.
        $tourHours = 0;
        foreach ($properties as $property) {
            if ($property->tourRules()->exists()) {
                $leftAlone[] = 'Tour hours '.$property->code;

                continue;
            }

            foreach (self::TOUR_HOURS as $weekday => [$from, $to]) {
                app(SaveTourRule::class)->handle($staff['property'], $property, null, ['weekday' => $weekday, 'start_time' => $from, 'end_time' => $to, 'slot_minutes' => 30, 'max_concurrent_tours' => 1]);
                $tourHours++;
            }
        }

        // Example testimonials and case studies by fictional people and companies, each saying so where it is shown.
        $testimonials = [];
        foreach ([
            'studio' => ['quote' => 'Example testimonial: our five-person team moved into a private cabin and finally had a quiet place to work, with a meeting room down the hall when clients visit.', 'person_title' => 'Founder', 'company_name' => 'Example Design Studio (fictional)'],
            'software' => ['quote' => 'Example testimonial: we book the conference room for our monthly training days, and the team arrives to a room that is ready to go.', 'person_title' => 'Operations lead', 'company_name' => 'Example Software Team (fictional)'],
        ] as $key => $testimonial) {
            $record = $find(Testimonial::class, 'quote', $testimonial['quote']);
            $publish($record, [...$testimonial, 'person_name' => 'Example member (fictional)', 'consent_on_file' => true, 'consent_reference' => self::EXAMPLE_CONSENT, 'statement_verified' => true]);
            $testimonials[$key] = $record;
        }

        $caseStudies = [
            ['slug' => 'example-design-studio', 'title' => 'Example: a design studio settles into a private cabin', 'company_name' => 'Example Design Studio (fictional)', 'testimonial' => 'studio', 'image' => 'cabins',
                'story' => '<p>Example text: a five-person design studio outgrew working from home and needed a quiet room of its own, with somewhere to meet clients.</p><p>SmartGro showed the team a private cabin and the shared meeting rooms, and the studio moved in the following month.</p>'],
            ['slug' => 'example-software-team', 'title' => 'Example: a software team trains in shared rooms', 'company_name' => 'Example Software Team (fictional)', 'testimonial' => 'software', 'image' => 'boardroom',
                'story' => '<p>Example text: a growing software team holds a training day every month and did not want a room standing empty the rest of the time.</p><p>The team books a conference room when it needs one and works from dedicated desks the rest of the month.</p>'],
        ];
        foreach ($caseStudies as $caseStudy) {
            $publish($find(CaseStudy::class, 'slug', $caseStudy['slug']), [
                'title' => $caseStudy['title'],
                'slug' => $caseStudy['slug'],
                'company_name' => $caseStudy['company_name'],
                'summary' => 'Example case study, about a fictional company, for SmartGro to replace with a real customer story published with that customer\'s consent.',
                'blocks' => [
                    $text('The story (example)', $caseStudy['story']),
                    $image($caseStudy['image']),
                    ['type' => 'testimonial', 'data' => ['testimonial_id' => $testimonials[$caseStudy['testimonial']]->id]],
                ],
                'consent_on_file' => true,
                'consent_reference' => self::EXAMPLE_CONSENT,
            ]);
        }

        return [
            'images' => count($images),
            'settings' => $settings,
            'pages' => ['legal/privacy'],
            'offerings' => $offerings,
            'locations' => $locations,
            'workspaces' => $workspaces,
            'tour_hours' => $tourHours,
            'testimonials' => count($testimonials),
            'case_studies' => count($caseStudies),
            'left_alone' => $leftAlone,
        ];
    }

    /**
     * The demo stock photos and the home banners' stock photos in the media library, by key, each added through the
     * upload action when it is not there yet (found by its title and origin).
     *
     * @return array<string, MediaAsset>
     */
    private function images(User $uploader): array
    {
        $images = collect(HomeShowcaseSeeder::IMAGES)->only(['loft_lounge', 'meeting_room', 'lounge'])->all();

        foreach (self::IMAGES as $key => $image) {
            $images[$key] = [
                'path' => 'database/seeders/media/demo/'.$image['file'],
                'origin' => MediaOrigin::LicensedStock,
                'title' => $image['title'],
                'alt_text' => $image['alt'],
                'licence_type' => 'CC0 1.0',
                'licence_holder' => $image['holder'].', via StockSnap',
                'licence_reference' => 'https://stocksnap.io/photo/'.$image['page'],
                'credit_line' => 'Photo: '.$image['holder'].' on StockSnap (CC0)',
                'usage_restrictions' => 'Stock photo, not a SmartGro location (OD-176). Replace with SmartGro photography when available.',
            ];
        }

        return array_map(fn (array $image): MediaAsset => $this->image($image, $uploader), $images);
    }

    /**
     * @param  array{path: string, origin: MediaOrigin, title: string, alt_text: string, licence_type?: string, licence_holder?: string, licence_reference?: string, credit_line?: string, usage_restrictions?: string}  $image
     */
    private function image(array $image, User $uploader): MediaAsset
    {
        $existing = MediaAsset::query()->where('title', $image['title'])->where('origin', $image['origin'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $path = base_path($image['path']);

        if (! is_file($path)) {
            throw new RuntimeException("The demo image {$image['path']} is missing.");
        }

        // The media library moves the file it is given, so it gets a copy: the original stays in the repository.
        $copy = tempnam(sys_get_temp_dir(), 'smartgro-demo-');
        copy($path, $copy);

        try {
            return app(CreateMediaAsset::class)->handle($uploader, new UploadedFile($copy, basename($path), null, null, true), [
                ...collect($image)->except(['path', 'origin'])->all(),
                'origin' => $image['origin']->value,
                'is_decorative' => false,
                'not_from_old_website' => true,
            ]);
        } finally {
            if (is_file($copy)) {
                unlink($copy);
            }
        }
    }
}
