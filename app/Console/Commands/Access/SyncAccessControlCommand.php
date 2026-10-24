<?php

namespace App\Console\Commands\Access;

use App\Actions\Access\SyncAccessControl;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('access:sync')]
#[Description('Create or update the staff roles and capabilities so they match the code (safe to run on every deployment)')]
class SyncAccessControlCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncAccessControl $syncAccessControl): int
    {
        $syncAccessControl->handle();

        $this->components->info('Staff roles and capabilities are in sync.');

        return self::SUCCESS;
    }
}
