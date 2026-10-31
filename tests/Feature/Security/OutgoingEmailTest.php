<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Notifications\MediaLicenceExpiring;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

/*
 * The emails SmartGro sends, built and sent through the test mailer (MAIL_MAILER=array) instead of being
 * faked, so that the MIME layer (symfony/mime, updated for CVE-2026-45070, P14-1) is exercised: the
 * licence reminder, Filament's password-reset link and Filament's emailed login code.
 */
beforeEach(function () {
    $this->staff = User::factory()->create(['name' => 'Test Staff', 'email' => 'staff@example.test']);
});

/**
 * @return list<SentMessage>
 */
function sentEmails(): array
{
    return array_values(app('mailer')->getSymfonyTransport()->messages()->all());
}

it('builds and sends each email with its subject, its text and HTML parts and nothing but its own recipient', function () {
    $asset = (new MediaAsset)->forceFill(['title' => 'Reception “north” view', 'licence_expires_at' => '2027-04-30']);
    $reset = new ResetPassword('test-token');
    $reset->url = 'https://example.test/admin/password-reset/reset?token=test-token';

    $this->staff->notify(new MediaLicenceExpiring($asset));
    $this->staff->notify($reset);
    $this->staff->notify(new VerifyEmailAuthentication('482913', 4));

    $sent = sentEmails();
    $emails = array_map(fn (SentMessage $message): Email => $message->getOriginalMessage(), $sent);

    expect($sent)->toHaveCount(3)
        ->and(array_map(fn (Email $email): string => $email->getTo()[0]->getAddress(), $emails))->toBe(['staff@example.test', 'staff@example.test', 'staff@example.test'])
        ->and($emails[0]->getSubject())->toBe('An image licence ends on 30 Apr 2027')
        ->and($emails[0]->getTextBody())->toContain('Reception “north” view')
        ->and($emails[1]->getTextBody())->toContain('https://example.test/admin/password-reset/reset?token=test-token')
        ->and($emails[2]->getTextBody())->toContain('482913');

    foreach ($sent as $message) {
        expect($message->toString())->toContain('Content-Type: multipart/alternative; boundary=')
            ->toContain('Content-Type: text/plain; charset=utf-8')
            ->toContain('Content-Type: text/html; charset=utf-8')
            ->not->toMatch('/^(Bcc|Cc):/mi');
    }
});

it('never lets a line break in a staff-entered title add a header to the email', function () {
    $asset = (new MediaAsset)->forceFill(['title' => "Lobby\r\nBcc: someone@example.test", 'licence_expires_at' => '2027-04-30']);

    $this->staff->notify(new MediaLicenceExpiring($asset));

    expect(sentEmails())->toHaveCount(1)
        ->and(sentEmails()[0]->toString())->not->toMatch('/^Bcc:/mi')
        ->and(sentEmails()[0]->getEnvelope()->getRecipients())->toHaveCount(1);
});
