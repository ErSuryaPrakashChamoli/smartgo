<?php

use App\Models\SecuritySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('keeps two-step login optional until it is switched on', function () {
    expect(SecuritySetting::requiresTwoStepLogin())->toBeFalse();

    SecuritySetting::query()->update(['two_step_login_required' => true]);

    expect(SecuritySetting::requiresTwoStepLogin())->toBeTrue();
});

it('always requires two-step login outside local development and the tests, even while it is switched off', function (string $environment) {
    app()->detectEnvironment(fn (): string => $environment);

    expect(SecuritySetting::requiresTwoStepLogin())->toBeTrue();
})->with(['production', 'staging']);

it('keeps two-step login optional on a local database that does not have the setting yet', function () {
    SecuritySetting::query()->update(['two_step_login_required' => true]);
    Schema::shouldReceive('hasTable')->once()->with('security_settings')->andReturnFalse();

    expect(SecuritySetting::requiresTwoStepLogin())->toBeFalse();
});
