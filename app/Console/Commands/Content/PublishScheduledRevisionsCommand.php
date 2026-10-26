<?php

namespace App\Console\Commands\Content;

use App\Actions\Content\PublishScheduledRevisions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:publish-scheduled')]
#[Description('Publish approved content whose scheduled time has come (run every minute by the scheduler)')]
class PublishScheduledRevisionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PublishScheduledRevisions $publishScheduledRevisions): int
    {
        $published = $publishScheduledRevisions->handle();

        $this->components->info("Published {$published} scheduled version(s).");

        return self::SUCCESS;
    }
}
