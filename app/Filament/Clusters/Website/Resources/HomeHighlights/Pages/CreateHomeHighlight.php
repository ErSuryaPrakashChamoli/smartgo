<?php

namespace App\Filament\Clusters\Website\Resources\HomeHighlights\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\HomeHighlights\HomeHighlightResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeHighlight extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = HomeHighlightResource::class;

    protected static bool $canCreateAnother = false;
}
