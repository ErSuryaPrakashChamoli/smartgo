<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    Notification::fake();

    $this->staff = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create([
        'password' => 'Correct-Synthetic-Pass-1',
    ]);
});

/**
 * Submits the password step and returns the page with the two-step challenge open, plus the emailed code.
 *
 * @return array{0: Testable, 1: string}
 */
function submitPasswordStep(User $user): array
{
    $page = Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'Correct-Synthetic-Pass-1'])
        ->call('authenticate');

    $code = null;
    Notification::assertSentTo($user, VerifyEmailAuthentication::class, function (VerifyEmailAuthentication $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return [$page, $code];
}

it('serves this sign-in page for the back office', function () {
    $this->get(Filament::getLoginUrl())->assertSeeLivewire(Login::class);
});

it('audits a wrong two-step login code without storing the code', function () {
    [$page, $code] = submitPasswordStep($this->staff);
    $wrongCode = str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

    $page->set('data.multiFactor.email_code.code', $wrongCode)->call('authenticate')->assertHasErrors();

    $entry = Activity::query()->where('event', 'two_factor_failed')->sole();

    expect($entry->subject->is($this->staff))->toBeTrue()
        ->and($entry->properties->keys()->sort()->values()->all())->toBe(['ip', 'user_agent'])
        ->and(Activity::query()->get()->toJson())->not->toContain($wrongCode);
    $this->assertGuest();
});

it('signs staff in with the right two-step login code and records no failure', function () {
    [$page, $code] = submitPasswordStep($this->staff);

    $page->set('data.multiFactor.email_code.code', $code)->call('authenticate')->assertHasNoErrors();

    $this->assertAuthenticatedAs($this->staff);
    expect(Activity::query()->where('event', 'login')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'two_factor_failed')->exists())->toBeFalse();
});

it('stops checking passwords after five sign-in attempts a minute', function () {
    $page = Livewire::test(Login::class)->fillForm(['email' => $this->staff->email, 'password' => 'Wrong-Synthetic-Pass-1']);

    foreach (range(1, 5) as $attempt) {
        $page->call('authenticate')->assertHasFormErrors(['email']);
    }

    $page->fillForm(['password' => 'Correct-Synthetic-Pass-1'])->call('authenticate')->assertNotified();

    expect(Activity::query()->where('event', 'login_failed')->count())->toBe(5);
    Notification::assertNothingSent();
    $this->assertGuest();
});

it('treats a wrong password as a failed sign-in, not a two-step failure', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => $this->staff->email, 'password' => 'Wrong-Synthetic-Pass-1'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Activity::query()->where('event', 'login_failed')->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'two_factor_failed')->exists())->toBeFalse();
    Notification::assertNothingSent();
    $this->assertGuest();
});
