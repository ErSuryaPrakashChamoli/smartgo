<?php

namespace Database\Seeders;

use App\Actions\Access\SyncAccessControl;
use Illuminate\Database\Seeder;

/**
 * The default seed (`php artisan db:seed`, `migrate:fresh --seed`; OD-166): the staff roles and their
 * capabilities, the owner-confirmed reference data and, outside production, the dummy staff with the
 * sample website content to change from the back office (SampleContentSeeder creates the staff when
 * they are missing, so running it again keeps their password), including the home page's sample banners
 * and highlights (HomeShowcaseSeeder, OD-169) and the example spaces under Spaces in the menu (SpaceListingSeeder,
 * OD-177), and the demo details for every part of the website that is still empty (DemoContentSeeder, OD-176:
 * placeholder contact details, a sample privacy notice, tour hours, the locations and their workspaces with
 * labelled stock photos, example case studies). Model events stay on: the audit log, the content workflow
 * and the permission cache depend on them.
 */
class DatabaseSeeder extends Seeder
{
    public function run(SyncAccessControl $syncAccessControl): void
    {
        $syncAccessControl->handle();

        $this->call(ReferenceDataSeeder::class);

        if (! app()->isProduction()) {
            $this->call(SampleContentSeeder::class);
            $this->call(HomeShowcaseSeeder::class);
            $this->call(DemoContentSeeder::class);
            // After the demo content, whose stock photos the example spaces use (OD-177 e).
            $this->call(SpaceListingSeeder::class);
        }
    }
}
