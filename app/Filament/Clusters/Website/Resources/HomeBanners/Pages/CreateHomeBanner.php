<?php

namespace App\Filament\Clusters\Website\Resources\HomeBanners\Pages;

use App\Filament\Clusters\Website\Concerns\CreatesFirstDraft;
use App\Filament\Clusters\Website\Resources\HomeBanners\HomeBannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeBanner extends CreateRecord
{
    use CreatesFirstDraft;

    protected static string $resource = HomeBannerResource::class;

    protected static bool $canCreateAnother = false;
}
