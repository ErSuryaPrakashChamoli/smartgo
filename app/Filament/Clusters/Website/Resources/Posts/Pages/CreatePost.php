<?php

namespace App\Filament\Clusters\Website\Resources\Posts\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = PostResource::class;

    protected static bool $canCreateAnother = false;
}
