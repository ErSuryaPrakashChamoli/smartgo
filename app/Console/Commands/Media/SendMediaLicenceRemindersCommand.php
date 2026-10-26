<?php

namespace App\Console\Commands\Media;

use App\Actions\Media\SendMediaLicenceReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:licence-reminders')]
#[Description('Remind media managers of image licences that end within 30 days (run daily by the scheduler)')]
class SendMediaLicenceRemindersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SendMediaLicenceReminders $sendMediaLicenceReminders): int
    {
        $count = $sendMediaLicenceReminders->handle();

        $this->components->info("Sent reminders for {$count} image licence(s).");

        return self::SUCCESS;
    }
}
