<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Models\CaseStudy;
use App\Models\MediaAsset;
use App\Models\Offering;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Website\EnquiryForm;
use App\Services\Website\TourForm;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DummyStaffSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);
});

/**
 * @return array{author: User, approver: User, operations: User, property: User}
 */
function demoStaff(): array
{
    $user = fn (string $key): User => User::query()->where('email', DummyStaffSeeder::email($key))->sole();

    return ['author' => $user('marketing'), 'approver' => $user('marketing2'), 'operations' => $user('operations'), 'property' => $user('property')];
}

it('fills every empty part of the website through the workflow, labelled as examples, and adds nothing when run again (OD-176)', function () {
    config(['backoffice.sample_content' => false]);
    $sources = collect(DemoContentSeeder::IMAGES)->mapWithKeys(fn (array $image): array => [$image['file'] => hash_file('sha256', database_path('seeders/media/demo/'.$image['file']))]);
    Artisan::call('db:seed', ['--class' => SampleContentSeeder::class]);

    Artisan::call('db:seed', ['--class' => DemoContentSeeder::class]);
    $revisions = Revision::query()->count();
    Artisan::call('db:seed', ['--class' => DemoContentSeeder::class]);

    $staff = demoStaff();
    $published = Revision::query()->where('state', RevisionState::Published)->get();
    $a70 = Property::query()->where('code', 'A-70')->sole();

    expect(config('backoffice.sample_content'))->toBeFalse()
        ->and(Revision::query()->count())->toBe($revisions)
        ->and(MediaAsset::query()->where('origin', MediaOrigin::LicensedStock)->whereNotNull('licence_reference')->whereNull('depicts_property_id')->count())->toBe(count(DemoContentSeeder::IMAGES) + 3)
        ->and($sources->every(fn (string $hash, string $file): bool => hash_file('sha256', database_path('seeders/media/demo/'.$file)) === $hash))->toBeTrue()
        ->and(SiteSetting::query()->where('handle', 'contact')->sole()->values)->toMatchArray(['phone' => '+91 00000 00000', 'email' => 'hello@smartgro.example', 'whatsapp' => '+910000000000'])
        ->and(SiteSetting::query()->where('handle', 'social')->sole()->published_at)->not->toBeNull()
        ->and(app(EnquiryForm::class)->isOpen())->toBeTrue()
        ->and(app(TourForm::class)->isOpen())->toBeTrue()
        ->and(app(TourForm::class)->isOffered())->toBeTrue()
        ->and(Property::query()->publiclyListed()->count())->toBe(4)
        ->and(Offering::query()->publiclyListed()->count())->toBe(28)
        ->and($a70->tourRules()->count())->toBe(6)
        ->and(Testimonial::query()->get()->every(fn (Testimonial $testimonial): bool => $testimonial->isShowable() && str_starts_with($testimonial->quote, 'Example testimonial:')))->toBeTrue()
        ->and(CaseStudy::query()->published()->pluck('slug')->all())->toEqualCanonicalizing(['example-design-studio', 'example-software-team'])
        ->and($published->every(fn (Revision $revision): bool => $revision->author_id === $staff['author']->id && $revision->reviewer_id === $staff['approver']->id))->toBeTrue();

    get('/legal/privacy')->assertOk()->assertSee('Sample content')->assertSee('This is a sample privacy notice, not SmartGro');
    get('/enquire')->assertOk()->assertDontSee('Online enquiries open soon');
    get($a70->path())->assertOk()->assertSee('Workspaces at SmartGro A-70')->assertSee('Stock photo, not a SmartGro location')->assertSee('Example text for SmartGro to replace');
    get($a70->path().'/hot-desks')->assertOk()->assertSee('Hot desks at SmartGro A-70')->assertSee('Stock photo, not a SmartGro location');
    get('/workspaces/hot-desks')->assertOk();
    get('/customers/example-design-studio')->assertOk()->assertSee('Example Design Studio (fictional)')->assertSee('Example testimonial:', false);
    get('/')->assertOk()->assertSee('+91 00000 00000')->assertSee('hello@smartgro.example')->assertDontSee('Contact details are on their way');
});

it('leaves alone content someone else published or is working on, and tour hours already set', function () {
    Artisan::call('db:seed', ['--class' => DemoContentSeeder::class]);
    $staff = demoStaff();
    $colleague = User::factory()->withEmailAuthentication()->create();
    $contact = SiteSetting::query()->where('handle', 'contact')->sole();
    $contact->publishedRevision->forceFill(['author_id' => $colleague->id])->save();
    $testimonial = Testimonial::query()->firstOrFail();
    app(CreateDraft::class)->handle($testimonial, $staff['author'], ['quote' => 'Changed by hand', 'person_name' => 'Someone']);
    $testimonial->openRevision()->forceFill(['author_id' => $colleague->id])->save();
    $contact->forceFill(['values' => [...$contact->values, 'phone' => '+91 12345 67890']])->save();

    $result = (new DemoContentSeeder)->seedWith($staff);

    expect($result['left_alone'])->toContain('SiteSetting contact')->toContain('Testimonial '.$testimonial->id)->toContain('Tour hours A-70')
        ->and($contact->fresh()->values['phone'])->toBe('+91 12345 67890')
        ->and($testimonial->fresh()->openRevision()->payload['quote'])->toBe('Changed by hand')
        ->and(Property::query()->where('code', 'A-70')->sole()->tourRules()->count())->toBe(6);
});

it('refuses to write demo content in production', function () {
    Artisan::call('db:seed', ['--class' => DummyStaffSeeder::class]);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => (new DemoContentSeeder)->seedWith(demoStaff()))->toThrow(RuntimeException::class, 'local databases only')
        ->and(MediaAsset::query()->count())->toBe(0);
});
