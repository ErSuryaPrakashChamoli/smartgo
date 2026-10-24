<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('audits turning on email two-step login', function () {
    $user = User::factory()->create();

    $user->toggleEmailAuthentication(true);

    $entry = Activity::query()->where('event', 'two_factor_changed')->sole();

    expect($entry->subject->is($user))->toBeTrue()
        ->and($entry->properties->all())->toEqual([
            'app_authentication' => false,
            'email_authentication' => true,
            'recovery_codes_remaining' => 0,
        ]);
});

it('audits app two-step login without storing the secret or recovery codes', function () {
    $user = User::factory()->create();

    $user->saveAppAuthenticationSecret('SYNTHETICSECRET234567');
    $user->saveAppAuthenticationRecoveryCodes(['synthetic-code-1', 'synthetic-code-2']);

    $latest = Activity::query()->where('event', 'two_factor_changed')->latest('id')->first();
    $everything = Activity::query()->get()->toJson();

    expect($latest->properties->all())->toEqual([
        'app_authentication' => true,
        'email_authentication' => false,
        'recovery_codes_remaining' => 2,
    ])->and($everything)->not->toContain('SYNTHETICSECRET234567')
        ->and($everything)->not->toContain('synthetic-code-1');
});

it('does not write a two-step login entry for other profile changes', function () {
    $user = User::factory()->create();

    $user->update(['name' => 'Renamed Synthetic User']);

    expect(Activity::query()->where('event', 'two_factor_changed')->exists())->toBeFalse();
});

it('starts new users as active before they are reloaded', function () {
    $user = User::factory()->create();

    expect($user->is_active)->toBeTrue();
});
