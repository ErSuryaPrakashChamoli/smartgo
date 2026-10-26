<?php

namespace App\Actions\Media;

use App\Enums\Access\Capability;
use App\Models\MediaAsset;
use App\Models\User;
use App\Notifications\MediaLicenceExpiring;
use Illuminate\Support\Facades\Notification;

/**
 * Warns media managers once, 30 days ahead, about every licence that is about to end (BR-MED-3).
 */
class SendMediaLicenceReminders
{
    public const int DAYS_AHEAD = 30;

    /**
     * @return int the number of assets reminded about
     */
    public function handle(): int
    {
        $assets = MediaAsset::query()
            ->whereNull('licence_reminder_sent_at')
            ->whereBetween('licence_expires_at', [today(), today()->addDays(self::DAYS_AHEAD)])
            ->get();

        if ($assets->isEmpty()) {
            return 0;
        }

        $managers = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->can(Capability::ManageMedia))
            ->values();

        foreach ($assets as $asset) {
            Notification::send($managers, new MediaLicenceExpiring($asset));
            $asset->forceFill(['licence_reminder_sent_at' => now()])->save();

            activity('content')->performedOn($asset)->event('media_licence_reminder')
                ->withProperties(['title' => $asset->title, 'licence_expires_at' => $asset->licence_expires_at?->toDateString(), 'recipients' => $managers->count()])
                ->log('Licence expiry reminder sent');
        }

        return $assets->count();
    }
}
