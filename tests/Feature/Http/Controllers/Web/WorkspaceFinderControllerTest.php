<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    $this->a70 = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $this->a71 = publishPropertyProfileForTest(Property::query()->where('code', 'A-71')->sole());
    offerForTest($this->a70, 'meeting_room');
});

/**
 * @param  array<string, mixed>  $choice
 */
function findWorkspace(array $choice): string
{
    return (string) get('/find-a-workspace?'.http_build_query($choice))->assertRedirect()->headers->get('Location');
}

it('sends a workspace type chosen at a location that offers it to that offering\'s page', function () {
    expect(findWorkspace(['workspace' => 'meeting-rooms', 'location' => $this->a70->id]))->toEndWith('/locations/noida/a-70/meeting-rooms');
});

it('sends a workspace type to its page when the chosen location does not offer it', function () {
    expect(findWorkspace(['workspace' => 'meeting-rooms', 'location' => $this->a71->id]))->toEndWith('/workspaces/meeting-rooms')
        ->and(findWorkspace(['workspace' => 'meeting-rooms']))->toEndWith('/workspaces/meeting-rooms');
});

it('sends a workspace type nobody offers yet to the enquiry form with the choice filled in, once the form is open', function () {
    expect(findWorkspace(['workspace' => 'virtual-office', 'location' => $this->a71->id]))->toEndWith('/locations/noida/a-71')
        ->and(findWorkspace(['workspace' => 'virtual-office']))->toEndWith('/#workspaces');

    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);

    expect(findWorkspace(['workspace' => 'virtual-office', 'location' => $this->a71->id]))->toEndWith('/enquire?workspace=virtual-office&property='.$this->a71->id)
        ->and(findWorkspace(['workspace' => 'virtual-office']))->toEndWith('/enquire?workspace=virtual-office');
});

it('sends a location on its own to its page, and no choice to all workspace types', function () {
    expect(findWorkspace(['workspace' => '', 'location' => $this->a71->id]))->toEndWith('/locations/noida/a-71')
        ->and(findWorkspace(['workspace' => '', 'location' => '']))->toEndWith('/workspaces')
        ->and(findWorkspace([]))->toEndWith('/workspaces');
});

it('ignores unlisted locations, inactive or unknown workspace types and malformed choices, and never leaves the website', function () {
    $planned = Property::query()->where('code', 'H-105')->sole();
    Product::query()->where('key', 'meeting_room')->update(['is_active' => false]);

    expect(findWorkspace(['location' => $planned->id]))->toEndWith('/workspaces')
        ->and(findWorkspace(['workspace' => 'meeting-rooms', 'location' => $this->a70->id]))->toEndWith('/locations/noida/a-70')
        ->and(findWorkspace(['workspace' => '//evil.example', 'location' => '1 OR 1=1']))->toEndWith('/workspaces')
        ->and(findWorkspace(['workspace' => ['private-cabins'], 'location' => [$this->a70->id]]))->toEndWith('/workspaces');

    foreach ([['workspace' => '//evil.example'], ['location' => 'https://evil.example']] as $choice) {
        expect(parse_url(findWorkspace($choice), PHP_URL_HOST))->toBe(parse_url((string) config('app.url'), PHP_URL_HOST));
    }
});

it('sends the visitor to the contact section while nothing is on the website', function () {
    Product::query()->update(['is_active' => false]);
    Property::query()->each(fn (Property $property) => $property->profile()->update(['status' => 'draft']));

    expect(findWorkspace([]))->toEndWith('/#contact');
});
