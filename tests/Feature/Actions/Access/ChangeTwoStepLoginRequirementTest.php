<?php

use App\Actions\Access\ChangeTwoStepLoginRequirement;
use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('makes two-step login compulsory and audits who did it', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    app(ChangeTwoStepLoginRequirement::class)->handle($superAdmin, true);

    $entry = Activity::query()->where('event', 'two_step_login_requirement_changed')->sole();

    expect(SecuritySetting::query()->sole()->two_step_login_required)->toBeTrue()
        ->and($entry->causer->is($superAdmin))->toBeTrue()
        ->and($entry->description)->toBe('Two-step login made compulsory')
        ->and($entry->properties->all())->toBe(['required' => true]);
});

it('makes two-step login optional again and audits who did it', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    SecuritySetting::query()->update(['two_step_login_required' => true]);

    app(ChangeTwoStepLoginRequirement::class)->handle($superAdmin, false);

    $entry = Activity::query()->where('event', 'two_step_login_requirement_changed')->sole();

    expect(SecuritySetting::query()->sole()->two_step_login_required)->toBeFalse()
        ->and($entry->description)->toBe('Two-step login made optional')
        ->and($entry->properties->all())->toBe(['required' => false]);
});

it('records nothing when the requirement does not change', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    app(ChangeTwoStepLoginRequirement::class)->handle($superAdmin, false);

    expect(Activity::query()->where('event', 'two_step_login_requirement_changed')->exists())->toBeFalse();
});

it('refuses staff who may not manage staff access', function () {
    $operationsManager = User::factory()->withRole(StaffRole::OperationsManager)->create();

    expect(fn () => app(ChangeTwoStepLoginRequirement::class)->handle($operationsManager, true))
        ->toThrow(AuthorizationException::class);

    expect(SecuritySetting::query()->sole()->two_step_login_required)->toBeFalse();
});

it('refuses to make two-step login optional outside local development and the tests', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    SecuritySetting::query()->update(['two_step_login_required' => true]);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(ChangeTwoStepLoginRequirement::class)->handle($superAdmin, false))
        ->toThrow(ValidationException::class, 'Two-step login is always required on this site.');

    expect(SecuritySetting::query()->sole()->two_step_login_required)->toBeTrue()
        ->and(Activity::query()->where('event', 'two_step_login_requirement_changed')->exists())->toBeFalse();
});
