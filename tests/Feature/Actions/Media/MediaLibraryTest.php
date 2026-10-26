<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Media\CreateMediaAsset;
use App\Actions\Media\DeleteMediaAsset;
use App\Actions\Media\UpdateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\User;
use App\Notifications\MediaLicenceExpiring;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    config(['app.url' => 'https://www.smartgro.example']);

    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function uploadImage(User $user, array $overrides = [], ?UploadedFile $file = null): MediaAsset
{
    return app(CreateMediaAsset::class)->handle($user, $file ?? UploadedFile::fake()->image('office.jpg', 1200, 800), [
        'title' => 'Reception area', 'origin' => MediaOrigin::ClientPhotography->value, 'alt_text' => 'Reception desk with plants',
        'not_from_old_website' => true, ...$overrides,
    ]);
}

/**
 * Publishes a page with an image block (and optionally a share image) through the workflow.
 */
function publishImagePage(MediaAsset $asset, User $author, User $approver, User $publisher, array $seo = []): Page
{
    $page = Page::factory()->create();
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Our space', 'slug' => 'our-space', 'template' => 'standard',
        'blocks' => [['type' => 'image', 'data' => ['media_asset_id' => $asset->id, 'alt' => 'Reception desk with plants', 'decorative' => false, 'caption' => 'Ground floor']]],
        'seo' => $seo,
    ]);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);

    return $page->fresh();
}

it('stores the original privately with WebP renditions, the size and an audit entry', function () {
    $asset = uploadImage($this->manager);

    expect($asset->file()->disk)->toBe('local')
        ->and($asset->dimensions())->toBe(['width' => 1200, 'height' => 800])
        ->and(collect(MediaAsset::RENDITIONS)->keys()->every(fn (string $name): bool => is_file($asset->file()->getPath($name))))->toBeTrue()
        ->and(Activity::query()->where('event', 'media_uploaded')->sole()->properties['confirmed_not_from_old_website'])->toBeTrue();
});

it('refuses files that are not JPEG, PNG or WebP images by their content, and other invalid uploads', function (Closure $file, array $overrides, string $field) {
    expect(fn () => uploadImage($this->manager, $overrides, $file()))->toThrow(ValidationException::class)
        ->and(MediaAsset::query()->count())->toBe(0);

    try {
        uploadImage($this->manager, $overrides, $file());
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'text renamed to .jpg' => [fn () => UploadedFile::fake()->createWithContent('photo.jpg', 'not an image at all'), [], 'file'],
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), [], 'file'],
    'too small' => [fn () => UploadedFile::fake()->image('tiny.jpg', 50, 50), [], 'file'],
    'no alt text and not decorative' => [fn () => UploadedFile::fake()->image('a.jpg', 800, 600), ['alt_text' => null], 'alt_text'],
    'licensed stock without licence details' => [fn () => UploadedFile::fake()->image('a.jpg', 800, 600), ['origin' => 'licensed_stock'], 'licence_holder'],
    'old website not ruled out' => [fn () => UploadedFile::fake()->image('a.jpg', 800, 600), ['not_from_old_website' => false], 'not_from_old_website'],
]);

it('lets a decorative image go without alt text', function () {
    expect(uploadImage($this->manager, ['alt_text' => null, 'is_decorative' => true])->is_decorative)->toBeTrue();
});

it('refuses staff without media.manage', function () {
    expect(fn () => uploadImage(User::factory()->withRole(StaffRole::Finance)->create()))->toThrow(AuthorizationException::class);
});

it('never changes an asset\'s origin when its details are edited', function () {
    $asset = uploadImage($this->manager, ['origin' => MediaOrigin::AiGeneratedConcept->value]);

    app(UpdateMediaAsset::class)->handle($asset, $this->manager, ['title' => 'Renamed', 'alt_text' => 'Concept of a lounge', 'origin' => 'client_photography']);

    expect($asset->fresh())->title->toBe('Renamed')->origin->toBe(MediaOrigin::AiGeneratedConcept);
});

it('serves renditions to visitors only once published content uses the image', function () {
    $asset = uploadImage($this->manager);

    get($asset->renditionPath('w480'))->assertNotFound();
    actingAs($this->manager)->get($asset->renditionPath('w480'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    auth()->logout();
    publishImagePage($asset, $this->manager, $this->approver, $this->publisher);

    get($asset->renditionPath('w480'))->assertOk()->assertHeader('Content-Type', 'image/webp');
    get('/media/'.$asset->id.'/original')->assertNotFound();
});

it('renders the image block with renditions, alt text, caption, credit and the concept label', function () {
    $asset = uploadImage($this->manager, ['origin' => MediaOrigin::Render->value, 'credit_line' => 'Render by the architect']);
    publishImagePage($asset, $this->manager, $this->approver, $this->publisher);

    get('/our-space')
        ->assertOk()
        ->assertSee('alt="Reception desk with plants"', false)
        ->assertSee('srcset="/media/'.$asset->id.'/w480 480w', false)
        ->assertSee('width="1200" height="800"', false)
        ->assertSee('Ground floor')
        ->assertSee('Render by the architect')
        ->assertSee('Concept visual');
});

it('takes an image off the website once its licence has expired', function () {
    $asset = uploadImage($this->manager, ['origin' => 'licensed_stock', 'licence_holder' => 'Stock Co', 'licence_reference' => 'L-1', 'licence_expires_at' => today()->addDay()->toDateString()]);
    publishImagePage($asset, $this->manager, $this->approver, $this->publisher);
    $this->travel(2)->days();

    get('/our-space')->assertOk()->assertDontSee('/media/'.$asset->id);
    get($asset->renditionPath('w960'))->assertNotFound();
});

it('publishes a share image with absolute social tags built from APP_URL', function () {
    $asset = uploadImage($this->manager);
    publishImagePage($asset, $this->manager, $this->approver, $this->publisher, ['image_asset_id' => $asset->id]);

    get('/our-space')
        ->assertSee('<meta property="og:image" content="https://www.smartgro.example/media/'.$asset->id.'/w1600">', false)
        ->assertSee('<meta property="og:url" content="https://www.smartgro.example/our-space">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

it('requires alt text for a non-decorative image block before submission', function () {
    $asset = uploadImage($this->manager);
    $draft = app(CreateDraft::class)->handle(Page::factory()->create(), $this->manager, [
        'title' => 'Our space', 'slug' => 'our-space', 'template' => 'standard',
        'blocks' => [['type' => 'image', 'data' => ['media_asset_id' => $asset->id, 'alt' => null, 'decorative' => false, 'caption' => null]]],
    ]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->manager))->toThrow(ValidationException::class);
});

it('refuses to delete an image that a page or version uses, and deletes it otherwise', function () {
    $used = uploadImage($this->manager);
    publishImagePage($used, $this->manager, $this->approver, $this->publisher);
    $unused = uploadImage($this->manager, ['title' => 'Unused']);

    expect(fn () => app(DeleteMediaAsset::class)->handle($used, $this->manager))->toThrow(ValidationException::class, 'cannot be deleted');

    app(DeleteMediaAsset::class)->handle($unused, $this->manager);

    expect(MediaAsset::query()->pluck('title')->all())->toBe(['Reception area']);
});

it('reminds media managers once, 30 days before a licence ends', function () {
    Notification::fake();
    uploadImage($this->manager, ['origin' => 'licensed_stock', 'licence_holder' => 'Stock Co', 'licence_reference' => 'L-1', 'licence_expires_at' => today()->addDays(20)->toDateString()]);
    uploadImage($this->manager, ['title' => 'Later', 'origin' => 'licensed_stock', 'licence_holder' => 'Stock Co', 'licence_reference' => 'L-2', 'licence_expires_at' => today()->addDays(90)->toDateString()]);

    $this->artisan('media:licence-reminders')->expectsOutputToContain('Sent reminders for 1 image licence(s).');
    $this->artisan('media:licence-reminders')->expectsOutputToContain('Sent reminders for 0 image licence(s).');

    Notification::assertSentTo([$this->manager, $this->approver, $this->publisher], MediaLicenceExpiring::class);
    Notification::assertCount(3);
});
