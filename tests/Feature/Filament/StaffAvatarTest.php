<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * The SVG inside an avatar data URI.
 */
function avatarSvg(User $user): string
{
    return base64_decode(str(app(InitialsAvatarProvider::class)->get($user))->after('data:image/svg+xml;base64,')->toString());
}

it('draws staff avatars in the page instead of sending names to another website', function () {
    app(SyncAccessControl::class)->handle();
    $staff = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create(['name' => 'Asha Kumar']);

    actingAs($staff)->get(Dashboard::getUrl())
        ->assertOk()
        ->assertDontSee('ui-avatars.com')
        ->assertSee('src="data:image/svg+xml;base64,', false);
});

it('shows up to two initials and copies nothing else from the name into the image', function () {
    expect(avatarSvg(User::factory()->make(['name' => 'asha devi kumar'])))->toContain('>AD</text>')
        ->and(avatarSvg(User::factory()->make(['name' => '<b>Ravi'])))->toContain('>B</text>')
        ->and(avatarSvg(User::factory()->make(['name' => '"&<'])))->not->toContain('<b')->toContain('></text>');
});
