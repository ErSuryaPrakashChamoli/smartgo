<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    Notification::fake();

    $this->sameAnswer = 'If this email address belongs to a staff account, a password reset link is on its way.';
});

it('serves this page for back-office password resets', function () {
    $this->get(Filament::getRequestPasswordResetUrl())->assertSeeLivewire(RequestPasswordReset::class);
});

it('emails active staff a reset link', function () {
    $staff = User::factory()->withRole(StaffRole::Finance)->create();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $staff->email])
        ->call('request')
        ->assertNotified($this->sameAnswer)
        ->assertSchemaStateSet(['email' => null]);

    Notification::assertSentTo($staff, ResetPassword::class);
});

it('gives the same answer for an address that has no account, and sends nothing', function () {
    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'nobody@example.test'])
        ->call('request')
        ->assertNotified($this->sameAnswer)
        ->assertSchemaStateSet(['email' => null]);

    Notification::assertNothingSent();
});

it('gives the same answer while a reset link for the account is being throttled', function () {
    $staff = User::factory()->withRole(StaffRole::Finance)->create();
    $page = Livewire::test(RequestPasswordReset::class);

    $page->fillForm(['email' => $staff->email])->call('request')->assertNotified($this->sameAnswer);
    $page->fillForm(['email' => $staff->email])->call('request')->assertNotified($this->sameAnswer);

    Notification::assertSentToTimes($staff, ResetPassword::class, 1);
});

it('gives the same answer for deactivated staff, and sends nothing', function () {
    $staff = User::factory()->withRole(StaffRole::Finance)->create(['is_active' => false]);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $staff->email])
        ->call('request')
        ->assertNotified($this->sameAnswer);

    Notification::assertNothingSent();
});
