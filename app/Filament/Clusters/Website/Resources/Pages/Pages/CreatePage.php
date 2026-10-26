<?php

namespace App\Filament\Clusters\Website\Resources\Pages\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Adds a page: an unpublished page and its first draft. Nothing is public until a version is
 * approved and published.
 */
class CreatePage extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;
}
