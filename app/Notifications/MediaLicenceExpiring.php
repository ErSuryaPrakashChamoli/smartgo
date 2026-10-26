<?php

namespace App\Notifications;

use App\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells media managers that an image's licence ends within 30 days (BR-MED-3). After that day the
 * image disappears from the website by itself.
 */
class MediaLicenceExpiring extends Notification
{
    use Queueable;

    public function __construct(public MediaAsset $asset) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('An image licence ends on '.$this->asset->licence_expires_at?->format('j M Y'))
            ->line("The licence for \u{201C}{$this->asset->title}\u{201D} ends on {$this->asset->licence_expires_at?->format('j M Y')}.")
            ->line('After that date the image is no longer shown on the website. Renew the licence and update its expiry date, or replace the image where it is used.');
    }
}
