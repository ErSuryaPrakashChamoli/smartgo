<?php

namespace App\Services\Website;

use App\Models\Page;
use Illuminate\Support\Facades\Crypt;

/**
 * Whether the public enquiry form is open, and what a visitor agrees to when sending it. The form
 * collects personal data, so it opens only once a privacy notice is published at /legal/privacy
 * (BR-PRV-1, BR-PRV-4, D-15): until then visitors are told to use the published contact details.
 * The notice's published version is recorded with every enquiry.
 */
class EnquiryForm
{
    public const string PRIVACY_NOTICE_SLUG = 'legal/privacy';

    public const string CONSENT_TEXT = 'SmartGro may contact me by email or phone about this enquiry.';

    /**
     * Seconds a person needs at least to fill in the form; quicker submissions are treated as spam.
     */
    public const int MINIMUM_SECONDS = 3;

    /**
     * Team size ranges offered on the form (the approved enquiry design).
     *
     * @var array<string, string>
     */
    public const array TEAM_SIZES = ['1' => 'Just me', '2-5' => '2 to 5 people', '6-20' => '6 to 20 people', '21-49' => '21 to 49 people', '50+' => '50 or more'];

    public function privacyNotice(): ?Page
    {
        return Page::query()->published()->where('slug', self::PRIVACY_NOTICE_SLUG)->with('publishedRevision')->first();
    }

    public function isOpen(): bool
    {
        return $this->privacyNotice() !== null;
    }

    /**
     * A tamper-proof note of when the form was shown, for the minimum fill time.
     */
    public function startedToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }
}
