<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\DummyStaffSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    app(SyncAccessControl::class)->handle();
});

/**
 * Run the seeder and return the password it printed.
 */
function seedDummyStaff(): string
{
    Artisan::call('db:seed', ['--class' => DummyStaffSeeder::class, '--force' => true]);
    preg_match('/\(shown only now\): (\S+)/', Artisan::output(), $match);

    return $match[1];
}

it('creates one active account per role, two for marketing, signing in with the printed password', function () {
    $password = seedDummyStaff();

    $accounts = User::query()->where('email', 'like', '%@dummy.test')->with('roles', 'assignedProperties')->orderBy('id')->get();
    expect($accounts->pluck('email')->all())->toBe(['admin@dummy.test', 'marketing@dummy.test', 'marketing2@dummy.test', 'operations@dummy.test', 'property@dummy.test', 'sales@dummy.test', 'finance@dummy.test'])
        ->and($accounts->map(fn (User $user): array => $user->roles->pluck('name')->all())->all())
        ->toBe([['super_admin'], ['marketing_manager'], ['marketing_manager'], ['operations_manager'], ['property_manager'], ['sales_crm_executive'], ['finance']]);
    expect($accounts->every(fn (User $user): bool => $user->is_active && Hash::check($password, $user->password) && $user->canAccessPanel(Filament::getPanel('admin'))))->toBeTrue();
    expect($accounts->mapWithKeys(fn (User $user): array => [$user->email => $user->assignedProperties->count()])->filter()->all())
        ->toBe(['property@dummy.test' => Property::query()->count(), 'sales@dummy.test' => Property::query()->count()]);
});

it('issues a new password when run again and keeps what staff changed on the accounts', function () {
    $first = seedDummyStaff();
    $sales = User::query()->where('email', 'sales@dummy.test')->sole();
    $sales->forceFill(['is_active' => false])->save();
    $sales->syncRoles([StaffRole::Finance]);
    $sales->assignedProperties()->sync([]);

    $second = seedDummyStaff();

    $sales->refresh();
    expect($second)->not->toBe($first)
        ->and(User::query()->where('email', 'like', '%@dummy.test')->count())->toBe(7)
        ->and(Hash::check($second, $sales->password))->toBeTrue()
        ->and($sales->is_active)->toBeFalse()
        ->and($sales->roles->pluck('name')->all())->toBe(['finance'])
        ->and($sales->assignedProperties()->count())->toBe(0);
});

it('refuses to create accounts in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DummyStaffSeeder::class)->setContainer(app())->__invoke())->toThrow(RuntimeException::class, 'local databases only');

    expect(User::query()->where('email', 'like', '%@dummy.test')->exists())->toBeFalse();
});
