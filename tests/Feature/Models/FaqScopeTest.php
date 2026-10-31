<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Clusters\Website\Resources\Faqs\Pages\EditFaq;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    app(SyncAccessControl::class)->handle();
    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->a70 = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $this->a71 = publishPropertyProfileForTest(Property::query()->where('code', 'A-71')->sole());
    $this->hotDesks = Product::query()->where('key', 'hot_desk')->sole();
    offerForTest($this->a70, 'hot_desk');
});

it('keeps a question\'s location or workspace type in its versions, never both, and only ones that exist', function () {
    $draft = app(CreateDraft::class)->handle(Faq::factory()->create(), $this->editor, ['question' => 'Is there parking at A-70?', 'answer' => 'Yes, in the basement.', 'property_id' => (string) $this->a70->id]);

    expect($draft->payload)->toMatchArray(['property_id' => $this->a70->id, 'product_id' => null])
        ->and(fn () => app(CreateDraft::class)->handle(Faq::factory()->create(), $this->editor, ['question' => 'Both?', 'answer' => 'No.', 'property_id' => $this->a70->id, 'product_id' => $this->hotDesks->id]))
        ->toThrow(ValidationException::class, 'A question is about one location or one workspace type, not both.')
        ->and(fn () => app(CreateDraft::class)->handle(Faq::factory()->create(), $this->editor, ['question' => 'Where?', 'answer' => 'Nowhere.', 'property_id' => 999999]))
        ->toThrow(ValidationException::class, 'The chosen location no longer exists.');
});

it('chooses where a question is shown from lists in the editor', function () {
    actingAs($this->editor);

    Livewire::test(CreateFaq::class)
        ->fillForm(['question' => 'Can I book a hot desk for a day?', 'answer' => 'Yes.', 'sort_order' => 1, 'shown_on' => 'workspace'])
        ->assertFormFieldHidden('property_id')
        ->fillForm(['product_id' => $this->hotDesks->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $faq = Faq::query()->sole();
    expect($faq->revisions()->sole()->payload)->toMatchArray(['product_id' => $this->hotDesks->id, 'property_id' => null]);

    Livewire::test(EditFaq::class, ['record' => $faq->getRouteKey()])
        ->assertFormSet(['shown_on' => 'workspace', 'product_id' => $this->hotDesks->id]);
});

it('lists general questions at /faqs, a location\'s own then the general ones on its page, and a workspace type\'s on its page', function () {
    Faq::factory()->published()->create(['question' => 'How do I pay?', 'sort_order' => 1]);
    Faq::factory()->published()->create(['question' => 'Is there parking at A-70?', 'property_id' => $this->a70->id, 'sort_order' => 5]);
    Faq::factory()->published()->create(['question' => 'Is A-71 open on Sundays?', 'property_id' => $this->a71->id]);
    Faq::factory()->published()->create(['question' => 'Can I book a hot desk for a day?', 'product_id' => $this->hotDesks->id]);
    Faq::factory()->create(['question' => 'A draft about A-70?', 'property_id' => $this->a70->id]);

    get('/faqs')->assertOk()->assertSee('How do I pay?')->assertDontSee('Is there parking at A-70?')->assertDontSee('Can I book a hot desk for a day?');

    get('/locations/noida/a-70')->assertOk()
        ->assertSee('Questions about SmartGro A-70')
        ->assertSee('href="#faqs"', false)
        ->assertSeeInOrder(['Is there parking at A-70?', 'How do I pay?'])
        ->assertDontSee('Is A-71 open on Sundays?')
        ->assertDontSee('A draft about A-70?')
        ->assertDontSee('Can I book a hot desk for a day?');

    get('/workspaces/hot-desks')->assertOk()
        ->assertSee('Questions about hot desks')
        ->assertSee('Can I book a hot desk for a day?')
        ->assertDontSee('How do I pay?')
        ->assertDontSee('Is there parking at A-70?');
});

it('shows no questions section on a page that has none', function () {
    get('/workspaces/hot-desks')->assertOk()->assertDontSee('Questions about hot desks');
    get('/locations/noida/a-70')->assertOk()->assertDontSee('Questions about SmartGro A-70');
});

it('keeps FAQs, their editor and the location and workspace pages working on a database without scopes, such as the owner\'s local one (OD-120)', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $table !== 'faqs' && $schema->hasColumns($table, $columns));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $schema->hasTable($table));
    $scopeQueries = [];
    DB::listen(function ($query) use (&$scopeQueries): void {
        if (str_contains($query->sql, 'faqs') && preg_match('/property_id|product_id/', $query->sql)) {
            $scopeQueries[] = $query->sql;
        }
    });
    Faq::factory()->published()->create(['question' => 'How do I pay?']);
    $draft = app(CreateDraft::class)->handle(Faq::factory()->create(), $this->editor, ['question' => 'Is there parking?', 'answer' => 'Yes.', 'property_id' => $this->a70->id]);
    actingAs($this->editor);

    get('/faqs')->assertOk()->assertSee('How do I pay?');
    get('/locations/noida/a-70')->assertOk()->assertSee('How do I pay?');
    get('/workspaces/hot-desks')->assertOk()->assertDontSee('Questions about hot desks');
    Livewire::test(EditFaq::class, ['record' => $draft->revisionable->getRouteKey()])->assertOk()->assertFormFieldHidden('shown_on');

    expect($scopeQueries)->toBe([])->and($draft->payload)->not->toHaveKey('property_id');
});
