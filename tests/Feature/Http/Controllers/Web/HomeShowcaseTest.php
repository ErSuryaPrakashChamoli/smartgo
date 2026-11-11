<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\HomeBanners\HomeBannerResource;
use App\Filament\Clusters\Website\Resources\HomeHighlights\HomeHighlightResource;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();

    $this->publish = function (Model $content, array $payload): Model {
        $draft = app(CreateDraft::class)->handle($content, $this->author, $payload);
        app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver), $this->author);

        return $content->refresh();
    };

    $this->image = fn (string $title, MediaOrigin $origin, array $details = []): MediaAsset => app(CreateMediaAsset::class)->handle($this->author, UploadedFile::fake()->image(str($title)->slug().'.jpg', 1600, 1000), [
        'title' => $title, 'origin' => $origin->value, 'alt_text' => 'Alt for '.$title, 'not_from_old_website' => true,
        'licence_holder' => 'Holder', 'licence_reference' => 'REF-1', ...$details,
    ]);

    $this->section = fn (string $label, string $section): array => ['label' => $label, 'type' => 'section', 'page_id' => null, 'section' => $section, 'product_id' => null, 'url' => null];
});

it('shows the published banners as a carousel in position order, each image labelled, with only the buttons that lead somewhere (OD-169)', function () {
    $ai = ($this->image)('AI interior', MediaOrigin::AiGeneratedConcept);
    $stock = ($this->image)('Stock lounge', MediaOrigin::LicensedStock);
    $expiring = ($this->image)('Expiring room', MediaOrigin::LicensedStock);

    ($this->publish)(HomeBanner::factory()->create(), ['heading' => 'Second banner', 'text' => 'Second text', 'media_asset_id' => $stock->id, 'sort_order' => 20,
        'primary_link' => ($this->section)('See workspace types', 'workspace_types'), 'secondary_link' => ($this->section)('Book a tour', 'book_a_tour')]);
    ($this->publish)(HomeBanner::factory()->create(), ['heading' => 'First banner', 'text' => 'First text', 'media_asset_id' => $ai->id, 'image_alt' => 'Banner alt', 'sort_order' => 10]);
    ($this->publish)(HomeBanner::factory()->create(), ['heading' => 'Expired banner', 'media_asset_id' => $expiring->id, 'sort_order' => 30]);
    $expiring->forceFill(['licence_expires_at' => now()->subDay()])->save();
    app(CreateDraft::class)->handle(HomeBanner::factory()->create(), $this->author, ['heading' => 'Draft banner', 'media_asset_id' => $stock->id]);

    get($stock->renditionPath('w960'))->assertOk();
    $html = get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('aria-roledescription="carousel"')
        ->toMatch('#<h1 id="home-heading"[^>]*>First banner</h1>.*<h2[^>]*>Second banner</h2>#s')
        ->toContain('aria-label="1 of 2"')->toContain('aria-label="2 of 2"')
        ->toContain($ai->renditionPath('w960'))->toContain('alt="Banner alt"')->toContain('AI-generated image, not a photo of a SmartGro location')
        ->toContain($stock->renditionPath('w960'))->toContain('alt="Alt for Stock lounge"')->toContain('Stock photo, not a SmartGro location')
        ->toContain('href="/workspaces"')->toContain('See workspace types')
        ->not->toContain('Book a tour')
        ->not->toContain('Expired banner')->not->toContain($expiring->renditionPath('w960'))
        ->not->toContain('Draft banner')
        ->not->toContain('/images/home/coworking-interior');
});

it('keeps the single, lower hero while no banner is published, and takes a banner off at once', function () {
    get('/')->assertOk()->assertSee('A better space')->assertSee('/images/home/coworking-interior-1024.webp', false)->assertDontSee('aria-roledescription="carousel"', false);

    $banner = ($this->publish)(HomeBanner::factory()->create(), ['heading' => 'Only banner', 'media_asset_id' => ($this->image)('Lounge', MediaOrigin::LicensedStock)->id]);

    get('/')->assertSee('Only banner')->assertDontSee('data-carousel-controls', false)->assertDontSee('/images/home/coworking-interior', false);

    app(UnpublishContent::class)->handle($banner, $this->author, 'Season over', false);

    get('/')->assertDontSee('Only banner')->assertSee('/images/home/coworking-interior-1024.webp', false);
});

it('shows the published highlights moving under the banners, says when they are example text, and otherwise shows facts from the records', function () {
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());

    $strip = fn (): string => str(get('/')->assertOk()->getContent())->after('data-marquee')->before('</section>')->toString();

    expect($strip())->toContain('Private offices and cabins')->toContain('Virtual office')->toContain('SmartGro A-70, Noida')->not->toContain('Example highlights');

    ($this->publish)(HomeHighlight::factory()->create(), ['text' => 'Second highlight', 'icon' => 'wifi', 'sort_order' => 20]);
    ($this->publish)(HomeHighlight::factory()->create(), ['text' => 'First highlight', 'icon' => 'bolt', 'sort_order' => 10]);

    expect($strip())->toMatch('#First highlight.*Second highlight#s')->toContain('Pause the highlights')
        ->not->toContain('Private offices and cabins')->not->toContain('Example highlights');

    config(['backoffice.sample_content' => true]);
    ($this->publish)(HomeHighlight::factory()->create(), ['text' => 'An example', 'is_sample' => true, 'sort_order' => 30]);

    expect($strip())->toContain('Example highlights')->toContain('An example');
});

it('refuses to submit example highlights where sample content is not allowed (OD-149)', function () {
    config(['backoffice.sample_content' => false]);
    $draft = app(CreateDraft::class)->handle(HomeHighlight::factory()->create(), $this->author, ['text' => 'Unconfirmed', 'is_sample' => true]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class, 'This website does not publish example text.');

    $own = app(CreateDraft::class)->handle(HomeHighlight::factory()->create(), $this->author, ['text' => 'Confirmed', 'is_sample' => false]);
    expect(app(SubmitRevision::class)->handle($own, $this->author)->state)->toBe(RevisionState::Submitted);
});

it('needs a heading and an image with a valid licence before a banner is submitted (BR-MED-3)', function () {
    $expired = ($this->image)('Expired', MediaOrigin::LicensedStock, ['licence_expires_at' => now()->subDay()->toDateString()]);
    $submit = fn (array $payload) => app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle(HomeBanner::factory()->create(), $this->author, $payload), $this->author);

    expect(fn () => $submit(['heading' => 'No image']))->toThrow(ValidationException::class, 'Choose the banner\'s image from the media library.')
        ->and(fn () => $submit(['heading' => 'Old image', 'media_asset_id' => $expired->id]))->toThrow(ValidationException::class, 'This image\'s licence has expired');
});

it('keeps the home page working without the banner and highlight tables, as on a database not migrated for them', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, ['home_banners', 'home_highlights'], true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'home_banners') || str_contains($query->sql, 'home_highlights')) {
            $queries[] = $query->sql;
        }
    });

    get('/')->assertOk()->assertSee('/images/home/coworking-interior-1024.webp', false)->assertSee('Private offices and cabins');
    actingAs($this->author)->get(HomeBannerResource::getUrl('index'))->assertForbidden();
    actingAs($this->author)->get(HomeHighlightResource::getUrl('index'))->assertForbidden();

    expect($queries)->toBe([]);
});

it('previews a banner version in the carousel and a highlight version in the strip', function () {
    $stock = ($this->image)('Stock lounge', MediaOrigin::LicensedStock);
    $banner = app(CreateDraft::class)->handle(HomeBanner::factory()->create(), $this->author, ['heading' => 'Draft banner heading', 'media_asset_id' => $stock->id]);
    $highlight = app(CreateDraft::class)->handle(HomeHighlight::factory()->create(), $this->author, ['text' => 'Draft highlight text', 'is_sample' => true]);

    actingAs($this->approver)->get(RevisionPreviewController::url($banner))->assertOk()
        ->assertSee('Preview: Home banner')->assertSee('Draft banner heading')->assertSee('Stock photo, not a SmartGro location')
        ->assertSee($stock->renditionPath('w960'), false)->assertDontSee('id="home-heading"', false);
    actingAs($this->approver)->get(RevisionPreviewController::url($highlight))->assertOk()
        ->assertSee('Draft highlight text')->assertSee('Example highlights');
});
