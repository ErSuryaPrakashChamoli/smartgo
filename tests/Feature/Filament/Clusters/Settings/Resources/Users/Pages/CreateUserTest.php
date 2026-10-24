<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('creates an active staff user with the chosen roles and audits the role grant', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($superAdmin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Synthetic Staff Member',
            'email' => 'synthetic.staff@example.test',
            'password' => 'Synthetic-Pass-2026',
            'roles' => [StaffRole::OperationsManager->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $created = User::query()->where('email', 'synthetic.staff@example.test')->sole();

    expect($created->is_active)->toBeTrue()
        ->and($created->getRoleNames()->all())->toBe(['operations_manager'])
        ->and(Hash::check('Synthetic-Pass-2026', $created->password))->toBeTrue()
        ->and(Activity::query()->where('event', 'roles_changed')->whereMorphedTo('subject', $created)->whereMorphedTo('causer', $superAdmin)->exists())->toBeTrue();
});

it('rejects a password that does not meet the staff password policy', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Synthetic Staff Member',
            'email' => 'synthetic.staff@example.test',
            'password' => 'short',
            'roles' => [StaffRole::Finance->value],
        ])
        ->call('create')
        ->assertHasFormErrors(['password'])
        ->assertSee('The password field must be at least 12 characters.');

    expect(User::query()->where('email', 'synthetic.staff@example.test')->exists())->toBeFalse();
});

it('requires a name, email, password and at least one role', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => null,
            'password' => null,
            'roles' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'password' => 'required', 'roles' => 'required']);
});

it('rejects an email address that another user already has', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Synthetic Staff Member',
            'email' => 'taken@example.test',
            'password' => 'Synthetic-Pass-2026',
            'roles' => [StaffRole::Finance->value],
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('rejects a role that is not one of the staff roles', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Synthetic Staff Member',
            'email' => 'synthetic.staff@example.test',
            'password' => 'Synthetic-Pass-2026',
            'roles' => ['owner'],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles.0' => 'in']);

    expect(User::query()->where('email', 'synthetic.staff@example.test')->exists())->toBeFalse();
});
