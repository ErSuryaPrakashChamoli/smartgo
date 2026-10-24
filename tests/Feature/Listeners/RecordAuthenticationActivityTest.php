<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\freezeSecond;

uses(RefreshDatabase::class);

it('records a sign-in and the time of the last sign-in', function () {
    freezeSecond();
    $user = User::factory()->create();

    Auth::login($user);

    $entry = Activity::query()->where('event', 'login')->sole();

    expect($user->fresh()->last_login_at->equalTo(now()))->toBeTrue()
        ->and($entry->subject->is($user))->toBeTrue()
        ->and($entry->causer->is($user))->toBeTrue()
        ->and($entry->properties->keys()->sort()->values()->all())->toBe(['ip', 'user_agent']);
});

it('records a failed sign-in with the email address but never the password', function () {
    User::factory()->create(['email' => 'staff@example.test']);

    Auth::attempt(['email' => 'staff@example.test', 'password' => 'Wrong-Synthetic-Pass-1']);

    $entry = Activity::query()->where('event', 'login_failed')->sole();

    expect($entry->properties['email'])->toBe('staff@example.test')
        ->and($entry->properties->toJson())->not->toContain('Wrong-Synthetic-Pass-1');
});

it('records a sign-out', function () {
    $user = User::factory()->create();
    Auth::login($user);

    Auth::logout();

    $entry = Activity::query()->where('event', 'logout')->sole();

    expect($entry->subject->is($user))->toBeTrue();
});
