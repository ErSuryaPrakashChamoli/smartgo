<?php

namespace App\Filament\Clusters\Website\Resources\SpaceListings\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\SpaceListings\SpaceListingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSpaceListing extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = SpaceListingResource::class;

    protected static bool $canCreateAnother = false;
}
